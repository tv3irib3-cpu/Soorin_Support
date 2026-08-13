<?php

namespace App\Http\Controllers\Portal;

use App\Auth\LoginAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        try {
            $user = (new LoginAttempt($data['identifier'], $data['password'], $request->boolean('remember')))
                ->authenticate();
        } catch (ValidationException $e) {
            throw $e;
        }

        if ($user->isSupportUser()) {
            auth()->logout();

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

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
