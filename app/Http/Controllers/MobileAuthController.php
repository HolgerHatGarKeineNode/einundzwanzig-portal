<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchNostrProfileJob;
use App\Models\LoginKey;
use App\Models\User;
use App\Support\NostrLogin;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Auth flow for the Einundzwanzig mobile app.
 *
 * The app opens /auth/mobile in an in-app browser. After a successful
 * Lightning (LNURL) or Nostr (NIP-55/Amber) login the flow issues a
 * Sanctum personal access token and hands it back to the app via the
 * einundzwanzig:// deep link.
 */
final class MobileAuthController extends Controller
{
    /** @var list<string> */
    public const ALLOWED_REDIRECT_URIS = ['einundzwanzig://auth'];

    public const DEFAULT_DEVICE_NAME = 'Einundzwanzig Mobile App';

    /**
     * Handle the NIP-55 signer callback (e.g. Amber on Android).
     *
     * The mobile login page opens a nostrsigner: URL containing an unsigned
     * kind-22242 event whose challenge tag carries the page's k1. The signer
     * appends the signed event JSON to this callback URL. On success a
     * LoginKey row is stored so the polling login page can complete the flow.
     */
    #[ExcludeRouteFromDocs]
    public function nostrCallback(Request $request): JsonResponse
    {
        $k1 = (string) $request->query('k1', '');

        if (! ctype_xdigit($k1) || strlen($k1) !== 64) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Invalid k1'], 400);
        }

        $signedEvent = json_decode((string) $request->query('event', ''), true);

        try {
            $npub = NostrLogin::verifyEvent($signedEvent, $k1);
        } catch (ValidationException) {
            Log::warning('Mobile Nostr auth verification failed', [
                'k1' => $k1,
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'ERROR', 'reason' => 'Signature was NOT VERIFIED'], 400);
        }

        $user = NostrLogin::findOrCreateUser($npub);
        FetchNostrProfileJob::dispatch($user);

        LoginKey::query()->updateOrCreate(
            ['k1' => $k1],
            ['user_id' => $user->id],
        );

        Log::info('Mobile Nostr auth successful', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['status' => 'OK']);
    }

    /**
     * Exchange a NIP-55-signed login event for a personal access token.
     *
     * Used by the mobile app when the signer callback opens the app
     * directly via a verified Android App Link: the app receives the
     * signed event in the deep-link path and trades it in here.
     */
    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'k1' => ['required', 'string', 'size:64'],
            'event' => ['required', 'array'],
            'device_name' => ['nullable', 'string', 'max:64'],
        ]);

        if (! ctype_xdigit($validated['k1'])) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Invalid k1'], 400);
        }

        try {
            $npub = NostrLogin::verifyEvent($validated['event'], $validated['k1']);
        } catch (ValidationException) {
            Log::warning('Mobile token exchange verification failed', [
                'k1' => $validated['k1'],
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'ERROR', 'reason' => 'Signature was NOT VERIFIED'], 400);
        }

        $user = NostrLogin::findOrCreateUser($npub);
        FetchNostrProfileJob::dispatch($user);

        $token = $this->createDeviceToken($user, $validated['device_name'] ?? self::DEFAULT_DEVICE_NAME);

        Log::info('Mobile app token issued via token exchange', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Revoke the personal access token that authenticated this request.
     *
     * Called by the mobile app on logout so the token does not linger
     * server-side after the app has deleted it from the device keystore.
     */
    public function revoke(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            Log::info('Mobile app token revoked', [
                'user_id' => $request->user()->id,
                'device_name' => $token->name,
            ]);
        }

        return response()->json(['status' => 'OK']);
    }

    /**
     * Headless Nostr launcher for the mobile app.
     *
     * The app opens this page in an in-app browser (Chrome Custom Tab). It
     * immediately launches the NIP-55 signer (e.g. Amber) via window.location
     * so the intent carries category.BROWSABLE — which routes Amber into its
     * web-signing flow (a direct ACTION_VIEW intent without that category
     * lands in Amber's app-to-app path and is rejected as malformed).
     *
     * The signer signs the kind-22242 challenge locally and opens the
     * /auth/mobile/signed callback, which issues the token and hands it back
     * to the app via the verified App Link. No relay, no visible login UI.
     */
    public function nostrLauncher(Request $request)
    {
        $deviceName = str((string) $request->query('device_name', self::DEFAULT_DEVICE_NAME))
            ->limit(64, '')
            ->whenEmpty(fn () => str(self::DEFAULT_DEVICE_NAME))
            ->value();

        // The signed callback issues the token and reads the device name
        // from this session (the callback shares the Custom Tab's cookies).
        $request->session()->put('mobile_auth', [
            'redirect_uri' => self::ALLOWED_REDIRECT_URIS[0],
            'device_name' => $deviceName,
        ]);

        $k1 = bin2hex(random_bytes(32));

        // The signer URI is assembled in the browser (see the view) with
        // encodeURIComponent(JSON.stringify(event)) — the exact encoding
        // Amber accepts. Building it server-side produced subtly different
        // percent-encoding that Amber rejected as malformed.
        //
        // The callback is the app's custom scheme, not a portal URL: the
        // signer opens it directly after signing, so the app receives the
        // signed event and exchanges it for a token via /api/mobile/token —
        // no browser handoff page (which a signer-owned Custom Tab failed to
        // display reliably).
        return view('auth.mobile-nostr-launch', [
            'k1' => $k1,
            'callbackUrl' => 'einundzwanzig://signed/'.$k1.'/',
        ]);
    }

    /**
     * Browser fallback for the app handoff URL (/app/auth?token=…).
     *
     * When the Android App Link is verified, navigation to this URL opens
     * the app directly and this route never renders. Without verification
     * (e.g. sideloaded build) the page offers the einundzwanzig:// deep
     * link behind a button — a user gesture, so Chrome opens the app
     * without its confirmation prompt.
     */
    public function handoff(Request $request)
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            return redirect()->route('auth.mobile');
        }

        return view('auth.app-handoff', [
            'deepLink' => self::ALLOWED_REDIRECT_URIS[0].'?token='.urlencode($token),
        ]);
    }

    /**
     * Handle the NIP-55 signer callback in its path-based form.
     *
     * Amber rebuilds the callback URL and drops its query string, so the
     * mobile login page hands out callback URLs of the form
     * /auth/mobile/signed/{k1}/ — the signer then appends the URL-encoded
     * signed event, which arrives here as the rest of the path. Runs in the
     * web middleware group: the signer opens the URL in the system browser,
     * which shares cookies with the in-app browser session, so the flow
     * state (device name, redirect target) is usually available and the
     * login can complete right here via the deep link bridge page.
     */
    public function signedCallback(Request $request, string $payload)
    {
        $k1 = substr($payload, 0, 64);

        if (strlen($payload) <= 64 || ! ctype_xdigit($k1)) {
            return redirect()->route('auth.mobile');
        }

        $signedEvent = json_decode(ltrim(substr($payload, 64), '/'), true);

        try {
            $npub = NostrLogin::verifyEvent($signedEvent, $k1);
        } catch (ValidationException) {
            Log::warning('Mobile Nostr auth verification failed (path callback)', [
                'k1' => $k1,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('auth.mobile');
        }

        $user = NostrLogin::findOrCreateUser($npub);
        FetchNostrProfileJob::dispatch($user);

        // Fallback for the polling login page in the in-app browser: if the
        // deep link below doesn't fire (cookie isolation, blocked scheme),
        // the original page still completes via checkAuth().
        LoginKey::query()->updateOrCreate(
            ['k1' => $k1],
            ['user_id' => $user->id],
        );

        Log::info('Mobile Nostr auth successful (path callback)', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return $this->handoffResponse($user, $request);
    }

    /**
     * Complete a mobile login after the wallet/signer callback has stored a
     * matching LoginKey row. Called as a full-page GET from the mobile login
     * component once wire:poll detects readiness (Lightning flow).
     */
    public function complete(Request $request, string $k1): mixed
    {
        $loginKey = LoginKey::query()
            ->where('k1', $k1)
            ->where('created_at', '>=', now()->subSeconds(NostrLogin::CHALLENGE_TTL_SECONDS))
            ->first();

        if (! $loginKey) {
            return redirect()->route('auth.mobile');
        }

        $user = User::find($loginKey->user_id);

        if (! $user) {
            return redirect()->route('auth.mobile');
        }

        return $this->handoffResponse($user, $request);
    }

    /**
     * Connect the app for a user who is already authenticated in the
     * in-app browser session (confirmation button on /auth/mobile).
     */
    public function confirm(Request $request): mixed
    {
        return $this->handoffResponse($request->user(), $request);
    }

    /**
     * Issue the token and render the handoff page, whose "back to app"
     * button carries the einundzwanzig:// deep link. The page is rendered
     * directly (rather than 302-redirecting to the /app/auth App Link)
     * because Chrome follows a server redirect internally and never
     * dispatches the App Link intent — the signed callback opens in the
     * browser, so the user lands here and taps once to return to the app.
     */
    private function handoffResponse(User $user, Request $request): View
    {
        return view('auth.app-handoff', [
            'deepLink' => $this->issueToken($user, $request),
        ]);
    }

    /**
     * Issue a personal access token for the session's device and build the
     * einundzwanzig:// deep link that hands it to the app.
     */
    private function issueToken(User $user, Request $request): string
    {
        $mobileAuth = (array) $request->session()->pull('mobile_auth', []);

        $deviceName = (string) ($mobileAuth['device_name'] ?? self::DEFAULT_DEVICE_NAME);

        $token = $this->createDeviceToken($user, $deviceName);

        return self::ALLOWED_REDIRECT_URIS[0].'?token='.urlencode($token->plainTextToken);
    }

    /**
     * Create a personal access token named after the device. Existing
     * tokens with the same device name are replaced so repeated logins
     * don't accumulate tokens.
     */
    private function createDeviceToken(User $user, string $deviceName): NewAccessToken
    {
        $deviceName = str($deviceName)
            ->limit(64, '')
            ->whenEmpty(fn () => str(self::DEFAULT_DEVICE_NAME))
            ->value();

        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName);

        Log::info('Mobile app token issued', [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ]);

        return $token;
    }
}
