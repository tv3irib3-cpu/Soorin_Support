<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="ocean">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.login') }} — {{ config('branding.company.name') }}</title>
    <link rel="icon" href="{{ asset(config('branding.logo.mark')) }}">
    <link rel="stylesheet" href="{{ route('theme.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Vazirmatn, system-ui, sans-serif;
            background: var(--nav); min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .login-card {
            background: var(--card); border-radius: var(--radius); padding: 32px 30px;
            width: 100%; max-width: 380px; box-shadow: 0 10px 40px rgba(0,0,0,.25);
        }
        .login-card img { height: 40px; display: block; margin: 0 auto 8px; }
        .login-card h1 { font-size: 16px; text-align: center; color: var(--text); margin: 0 0 4px; }
        .login-card p.sub { text-align: center; color: var(--muted); font-size: 12.5px; margin: 0 0 22px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 5px; font-size: 13px; color: var(--muted); }
        .field input {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;
            font-family: inherit; font-size: 14px;
        }
        .error-box {
            background: #fee2e2; color: #b91c1c; border-radius: 8px; padding: 10px 14px;
            font-size: 12.5px; margin-bottom: 16px;
        }
        button.submit {
            width: 100%; background: var(--accent); color: #fff; border: none; border-radius: 8px;
            padding: 11px; font-family: inherit; font-size: 14px; cursor: pointer; margin-top: 6px;
        }
        .login-footer { margin-top: 18px; }
        .login-footer .app-footer { border-top: none; margin-top: 0; }
        .login-footer .app-footer__copy,
        .login-footer .app-footer__meta { color: rgba(255,255,255,.55); }
        .login-footer .app-footer__meta a { color: rgba(255,255,255,.85); }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="{{ asset(config('branding.logo.mark')) }}" alt="" onerror="this.style.display='none'">
        <h1>{{ config('branding.company.name') }}</h1>
        <p class="sub">{{ __('portal.title') }} — {{ __('auth.portal_welcome') }}</p>

        @if ($errors->any())
            <div class="error-box">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('portal.login') }}">
            @csrf
            <div class="field">
                <label for="identifier">{{ __('auth.identifier') }}</label>
                <input type="text" id="identifier" name="identifier" value="{{ old('identifier') }}" required autofocus dir="ltr" style="text-align:right">
            </div>
            <div class="field">
                <label for="password">{{ __('auth.password_field') }}</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit">{{ __('auth.login_action') }}</button>
        </form>
    </div>

    <div class="login-footer">
        <x-footer />
    </div>
</body>
</html>
