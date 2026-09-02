<?php

namespace App\Http\Controllers\Portal;

use App\Auth\LoginAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show(): \Illuminate\View\View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ]);

        // محدودیتِ تلاش برای ورود — پرتال روی اینترنت است و بدونِ این، در برابرِ
        // حدسِ رمز (brute-force) بی‌دفاع بود. ۵ تلاشِ ناموفق در دقیقه به‌ازای هر
        // (شناسه + IP)؛ ورودِ موفق شمارنده را صفر می‌کند.
        $throttleKey = Str::transliterate(Str::lower($data['identifier']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        try {
            $user = (new LoginAttempt($data['identifier'], $data['password'], $request->boolean('remember')))
                ->authenticate();
        } catch (ValidationException $e) {
            RateLimiter::hit($throttleKey);

            throw $e;
        }

        // کاربرِ پشتیبان نباید از پرتال وارد شود — این هم یک تلاشِ ناموفقِ پرتال است.
        if ($user->isSupportUser()) {
            auth()->logout();
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
