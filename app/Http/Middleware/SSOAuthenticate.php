<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SSOAuthenticate
{
    public function handle(Request $request, Closure $next): mixed
    {
        // 1. Pass through if already authenticated locally
        if (Auth::check()) {
            return $next($request);
        }

        // 2. Check if returning from CLMS with an SSO payload
        if ($request->has(['user_id', 'email', 'expires', 'nonce', 'signature'])) {
            return $this->handleSSOCallback($request);
        }

        // 3. Redirect to CLMS Provider to verify session
        $providerUrl = rtrim(config('services.sso.provider_url'), '/') . '/sso/authorize';
        $currentUrl = $request->fullUrl();

        return redirect()->away($providerUrl . '?redirect_url=' . urlencode($currentUrl));
    }

    protected function handleSSOCallback(Request $request): RedirectResponse
    {
        $userId    = $request->query('user_id');
        $email     = $request->query('email');
        $expires   = (int) $request->query('expires');
        $nonce     = $request->query('nonce');
        $signature = $request->query('signature');

        // Security Check 1: Token Expiration
        if (now()->timestamp > $expires) {
            abort(403, 'SSO token has expired. Please try logging in again.');
        }

        // Security Check 2: Prevent Replay Attacks (Ensure nonce hasn't been used)
        $cacheKey = 'sso_nonce_' . $nonce;
        if (Cache::has($cacheKey)) {
            abort(403, 'SSO token has already been used.');
        }

        // Security Check 3: HMAC Signature Verification
        $payload = "{$userId}|{$email}|{$expires}|{$nonce}";
        $expectedSignature = hash_hmac('sha256', $payload, config('services.sso.secret'));

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid SSO signature verification.');
        }

        // Mark nonce as used until the expiration timestamp
        $secondsRemaining = max(1, $expires - now()->timestamp);
        Cache::put($cacheKey, true, $secondsRemaining);

        // Find or auto-provision local user account
        $user = User::where('email', $email)->first();

        if (! $user) {
            abort(404, 'User account does not exist in the secondary application.');
        }

        // Log the user in locally
        Auth::login($user, true);

        // Redirect to clean target URL (removes sso query params from browser address bar)
        return redirect()->to($request->url());
    }
}
