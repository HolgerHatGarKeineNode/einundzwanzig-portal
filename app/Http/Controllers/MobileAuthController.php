<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchNostrProfileJob;
use App\Models\LoginKey;
use App\Models\User;
use App\Support\NostrLogin;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
     * Complete a mobile login after the wallet/signer callback has stored a
     * matching LoginKey row. Called as a full-page GET from the mobile login
     * component once wire:poll detects readiness. Issues the token and
     * redirects into the app via the deep link.
     */
    public function complete(Request $request, string $k1): RedirectResponse
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

        return $this->issueTokenAndRedirect($user, $request);
    }

    /**
     * Connect the app for a user who is already authenticated in the
     * in-app browser session (confirmation button on /auth/mobile).
     */
    public function confirm(Request $request): RedirectResponse
    {
        return $this->issueTokenAndRedirect($request->user(), $request);
    }

    /**
     * Issue a personal access token named after the device and hand it to
     * the app via the whitelisted deep link. Existing tokens with the same
     * device name are replaced so repeated logins don't accumulate tokens.
     */
    private function issueTokenAndRedirect(User $user, Request $request): RedirectResponse
    {
        $mobileAuth = (array) $request->session()->pull('mobile_auth', []);

        $redirectUri = $mobileAuth['redirect_uri'] ?? self::ALLOWED_REDIRECT_URIS[0];

        if (! in_array($redirectUri, self::ALLOWED_REDIRECT_URIS, true)) {
            $redirectUri = self::ALLOWED_REDIRECT_URIS[0];
        }

        $deviceName = str((string) ($mobileAuth['device_name'] ?? self::DEFAULT_DEVICE_NAME))
            ->limit(64, '')
            ->whenEmpty(fn () => str(self::DEFAULT_DEVICE_NAME))
            ->value();

        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName);

        Log::info('Mobile app token issued', [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ]);

        return redirect()->away($redirectUri.'?token='.urlencode($token->plainTextToken));
    }
}
