<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="ocean">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.login') }} — {{ \App\Support\Branding::companyName() }}</title>
    <x-favicon />
    <x-theme-css />
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Vazirmatn, system-ui, sans-serif; color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
            /* پس‌زمینهٔ برند با درخششِ فیروزه‌ای — مثلِ حال‌وهوای ورودِ پشتیبان */
            background:
                radial-gradient(1100px 560px at 100% -10%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 60%),
                radial-gradient(820px 480px at -10% 110%, color-mix(in srgb, var(--accent) 15%, transparent), transparent 55%),
                var(--nav);
        }
        .login-card {
            background: var(--card);
            border: 1px solid color-mix(in srgb, var(--border) 60%, transparent);
            border-radius: 18px; padding: 36px 32px 24px; width: 100%; max-width: 400px;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .brand { text-align: center; margin-bottom: 24px; }
        .brand img { height: 56px; width: auto; display: inline-block; margin-bottom: 12px; }
        .brand h1 { font-size: 18px; font-weight: 800; color: var(--text); margin: 0 0 4px; }
        .brand p { color: var(--muted); font-size: 12.5px; line-height: 1.7; margin: 0; }
        .field { margin-bottom: 15px; }
        .field label { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: var(--muted); }
        .field input {
            width: 100%; padding: 11px 13px; border: 1px solid var(--border); border-radius: 10px;
            font-family: inherit; font-size: 14px; color: var(--text); background: var(--bg);
            transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent);
        }
        .remember { display: flex; align-items: center; gap: 8px; margin: 2px 0 18px; font-size: 12.5px; color: var(--muted); cursor: pointer; }
        .remember input { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
        .error-box {
            background: color-mix(in srgb, var(--danger) 12%, transparent); color: var(--danger);
            border: 1px solid color-mix(in srgb, var(--danger) 30%, transparent);
            border-radius: 10px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px;
        }
        button.submit {
            width: 100%; background: var(--accent); color: #fff; border: none; border-radius: 10px;
            padding: 12px; font-family: inherit; font-size: 14.5px; font-weight: 700; cursor: pointer;
            transition: filter .15s, transform .05s;
        }
        button.submit:hover { filter: brightness(1.06); }
        button.submit:active { transform: translateY(1px); }
        .login-footer { margin-top: 20px; }
        .login-footer .app-footer { border-top: none; margin-top: 0; }
        .login-footer .app-footer__copy,
        .login-footer .app-footer__meta { color: rgba(255,255,255,.6); }
        .login-footer .app-footer__meta a { color: rgba(255,255,255,.9); }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            {{-- لوگو از سیستمِ برندینگ خوانده می‌شود؛ با شخصی‌سازی خودکار عوض می‌شود --}}
            <img src="{{ \App\Support\Branding::logo('light') }}" alt="{{ \App\Support\Branding::companyName() }}" onerror="this.style.display='none'">
            <h1>{{ \App\Support\Branding::companyName() }}</h1>
            <p>{{ __('portal.title') }} — {{ __('auth.portal_welcome') }}</p>
        </div>

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
            <label class="remember">
                <input type="checkbox" name="remember" value="1">
                {{ __('auth.remember_me') }}
            </label>
            <button type="submit" class="submit">{{ __('auth.login_action') }}</button>
        </form>
    </div>

    <div class="login-footer">
        <x-footer />
    </div>
</body>
</html>
