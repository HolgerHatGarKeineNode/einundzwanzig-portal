<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LoginKey;
use App\Models\User;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use eza\lnurl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class LnurlAuthController extends Controller
{
    /**
     * Handle LNURL authentication callback.
     *
     * This endpoint is called by Lightning wallets during LNURL-Auth authentication flow.
     * It validates the signature provided by wallet against the stored challenge (k1).
     */
    #[ExcludeRouteFromDocs]
    public function callback(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'k1' => ['required', 'string', 'size:64'],
                'sig' => ['required', 'string'],
                'key' => ['required', 'string', 'min:64', 'max:66'],
            ]);

            // Validate hex format manually
            if (! ctype_xdigit($validated['k1'])) {
                throw ValidationException::withMessages([
                    'k1' => ['The k1 field must be a valid hexadecimal string.'],
                ]);
            }

            if (! ctype_xdigit($validated['key'])) {
                throw ValidationException::withMessages([
                    'key' => ['The key field must be a valid hexadecimal string.'],
                ]);
            }

            $isVerified = lnurl\auth($validated['k1'], $validated['sig'], $validated['key']);

            if (! $isVerified) {
                Log::warning('LNURL auth verification failed', [
                    'k1' => $validated['k1'],
                    'public_key' => $validated['key'],
                    'reason' => 'Signature verification failed',
                    'ip' => $request->ip(),
                ]);

                return $this->errorResponse('Signature was NOT VERIFIED');
            }

            $user = $this->findOrCreateUser($validated['k1'], $validated['key']);

            LoginKey::query()->updateOrCreate(
                ['k1' => $validated['k1']],
                ['user_id' => $user->id],
            );

            Log::info('LNURL auth successful', [
                'user_id' => $user->id,
                'public_key' => $validated['key'],
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'OK']);
        } catch (ValidationException $e) {
            Log::warning('LNURL auth validation failed', [
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Invalid request parameters');
        } catch (\ErrorException $e) {
            Log::error('LNURL auth error from elliptic library', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'k1' => $request->input('k1'),
                'key' => $request->input('key'),
                'sig' => $request->input('sig'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Wallet signature format incompatible. Please try a different wallet.');
        } catch (\Throwable $e) {
            Log::error('LNURL auth unexpected error', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'k1' => $request->input('k1'),
                'key' => $request->input('key'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Authentication failed. Please try again.');
        }
    }

    /**
     * Find or create a user based on authentication flow.
     *
     * First tries to find an existing user with a matching k1 challenge.
     * If found, updates their public key. Otherwise, looks for a user by public key.
     * If still not found, creates a new user.
     *
     * @param  string  $k1  The challenge identifier
     * @param  string  $publicKey  The wallet's public key
     */
    private function findOrCreateUser(string $k1, string $publicKey): User
    {
        $user = User::query()
            ->where('change', $k1)
            ->where('change_time', '>', now()->subMinutes(5))
            ->first();

        if ($user) {
            $user->public_key = $publicKey;
            $user->change = null;
            $user->change_time = null;
            $user->save();

            return $user;
        }

        $user = User::query()
            ->whereBlind('public_key', 'public_key_index', $publicKey)
            ->first();

        if ($user) {
            return $user;
        }

        $fakeName = str()->random(10);

        return User::create([
            'public_key' => $publicKey,
            'is_lecturer' => true,
            'name' => $fakeName,
            'email' => str($publicKey)->substr(-12).'@portal.einundzwanzig.space',
            'lnbits' => [
                'read_key' => null,
                'url' => null,
                'wallet_id' => null,
            ],
        ]);
    }

    /**
     * Return an LNURL-compliant error response.
     *
     * @param  string  $reason  The error reason
     */
    private function errorResponse(string $reason): JsonResponse
    {
        return response()->json([
            'status' => 'ERROR',
            'reason' => $reason,
        ], 400);
    }

    /**
     * Complete a Lightning login after the wallet callback has stored a
     * matching LoginKey row. Called as a full-page GET from the login
     * component once wire:poll detects readiness.
     *
     * The wire:poll handler itself must not call Auth::login(), since that
     * rotates the session id and CSRF token mid-flight — any parallel
     * Livewire request in the same window (a sibling component, a stray
     * poll tick) would then 419. By handing off to this controller, the
     * session migration happens during a clean, non-Livewire request.
     */
    public function completeLogin(string $k1): RedirectResponse
    {
        if (! ctype_xdigit($k1) || strlen($k1) !== 64) {
            return redirect()->route('login');
        }

        $loginKey = LoginKey::query()
            ->where('k1', $k1)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if (! $loginKey) {
            return redirect()->route('login');
        }

        $user = User::find($loginKey->user_id);

        if (! $user) {
            return redirect()->route('login');
        }

        // Auth::login() calls Session::migrate(destroy: true) internally,
        // which wipes the previous session payload. Capture lang_country
        // and the post-login intended URL before the login and restore them
        // on the fresh session. The intended URL lets the OAuth2 flow resume:
        // a guest who clicked "Connect" in an MCP client is bounced to login
        // and, after logging in, is sent back to /oauth/authorize instead of
        // landing on the dashboard.
        $langCountry = session('lang_country', config('app.domain_country'));
        $intendedUrl = session('url.intended');

        Auth::login($user);

        session(['lang_country' => $langCountry]);

        if ($intendedUrl !== null) {
            session(['url.intended' => $intendedUrl]);
        }

        $country = str($langCountry)
            ->after('-')
            ->lower()
            ->value();

        return redirect()->intended(route('dashboard', ['country' => $country]));
    }

    /**
     * Check for authentication errors based on k1 challenge.
     *
     * This endpoint is polled by the frontend to detect authentication failures.
     */
    #[ExcludeRouteFromDocs]
    public function checkError(Request $request): JsonResponse
    {
        $k1 = $request->input('k1');
        $elapsedSeconds = $request->input('elapsed_seconds', 0);

        if (! $k1) {
            return response()->json(['error' => null]);
        }

        $loginKey = LoginKey::query()
            ->where('k1', $k1)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($loginKey) {
            return response()->json(['error' => null]);
        }

        if ($elapsedSeconds >= 300) {
            return response()->json([
                'error' => 'Session expired. Please try again.',
            ]);
        }

        return response()->json(['error' => null]);
    }
}
