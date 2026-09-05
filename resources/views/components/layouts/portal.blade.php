<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="{{ $activeTheme ?? config('branding.default_theme') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('portal.title') }} — {{ \App\Support\Branding::companyName() }}</title>
    <x-favicon />
    <link rel="stylesheet" href="{{ route('theme.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: Vazirmatn, system-ui, sans-serif; color: var(--text);
            background:
                radial-gradient(1100px 420px at 100% -5%, color-mix(in srgb, var(--accent) 10%, transparent), transparent 60%),
                var(--bg);
            min-height: 100vh;
        }

        /* ---------- هدر ---------- */
        .portal-header {
            background: var(--nav); color: var(--nav-on);
            padding: 0 22px; display: flex; align-items: center; justify-content: space-between;
            gap: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.18); position: sticky; top: 0; z-index: 20;
        }
        .portal-header__brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 15px; padding: 12px 0; }
        .portal-header__brand img { height: 34px; width: auto; }
        .portal-header__nav { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
        .portal-header__nav a {
            color: var(--nav-text); text-decoration: none; font-size: 13.5px; font-weight: 600;
            padding: 8px 14px; border-radius: 9px; transition: background .15s, color .15s;
        }
        .portal-header__nav a:hover { color: var(--nav-on); background: rgba(255,255,255,.06); }
        .portal-header__nav a.active { color: #fff; background: var(--nav-active); }
        .portal-header__user { display: flex; align-items: center; gap: 10px; margin-inline-start: 8px; }
        .portal-header__chip {
            display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--nav-on);
            background: rgba(255,255,255,.08); padding: 6px 12px; border-radius: 999px;
        }
        .portal-header__chip .avatar {
            width: 26px; height: 26px; border-radius: 50%; background: var(--nav-active); color: #fff;
            display: grid; place-items: center; font-size: 12px; font-weight: 700;
        }
        .portal-header__user form button {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.14); color: var(--nav-text);
            font-family: inherit; font-size: 12.5px; cursor: pointer; padding: 7px 12px; border-radius: 9px;
            transition: background .15s, color .15s;
        }
        .portal-header__user form button:hover { background: rgba(255,255,255,.12); color: #fff; }

        /* ---------- بدنه ---------- */
        .portal-main { max-width: 1000px; margin: 26px auto 40px; padding: 0 18px; min-height: 55vh; }

        .page-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .page-head h1 { font-size: 19px; font-weight: 800; margin: 0; }
        .page-head .sub { color: var(--muted); font-size: 13px; margin-top: 2px; }

        .card {
            background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px;
            box-shadow: 0 6px 22px rgba(0,0,0,.05);
        }
        .card + .card { margin-top: 16px; }

        /* کارت‌های آمار داشبورد */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
        .stat {
            background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px;
            display: flex; align-items: center; gap: 14px; box-shadow: 0 6px 22px rgba(0,0,0,.05);
        }
        .stat__icon {
            width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; flex: none;
            background: var(--accent-soft); color: var(--accent-text);
        }
        .stat__icon svg { width: 22px; height: 22px; }
        .stat__num { font-size: 24px; font-weight: 800; line-height: 1.1; }
        .stat__label { color: var(--muted); font-size: 12.5px; margin-top: 3px; }

        /* ---------- دکمه‌ها ---------- */
        .btn {
            display: inline-flex; align-items: center; gap: 7px; background: var(--accent); color: #fff; border: none;
            border-radius: 10px; padding: 10px 18px; font-family: inherit; font-size: 13.5px; font-weight: 700;
            cursor: pointer; text-decoration: none; transition: filter .15s, transform .05s;
        }
        .btn:hover { filter: brightness(1.07); }
        .btn:active { transform: translateY(1px); }
        .btn svg { width: 17px; height: 17px; }
        .btn.secondary { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn.secondary:hover { background: color-mix(in srgb, var(--accent) 8%, transparent); filter: none; }

        /* ---------- فرم ---------- */
        .field { margin-bottom: 15px; }
        .field label { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: var(--muted); }
        .field input, .field select, .field textarea {
            width: 100%; padding: 10px 13px; border: 1px solid var(--border); border-radius: 10px;
            font-family: inherit; font-size: 14px; background: var(--bg); color: var(--text);
            transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 22%, transparent);
        }
        .field .error { color: var(--danger); font-size: 12px; margin-top: 4px; }

        /* ---------- نشان‌ها ---------- */
        .badge { display: inline-block; padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
        .badge.success { background: var(--accent-soft); color: var(--accent-text); }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.gray { background: color-mix(in srgb, var(--muted) 16%, transparent); color: var(--muted); }
        .badge.danger { background: color-mix(in srgb, var(--danger) 14%, transparent); color: var(--danger); }

        .status-banner { padding: 13px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 13.5px; }
        .status-banner.success { background: var(--accent-soft); color: var(--accent-text); }
        .status-banner.warning { background: #fef3c7; color: #92400e; }

        /* ---------- جدول ---------- */
        table.simple { width: 100%; border-collapse: collapse; }
        table.simple th { text-align: right; font-size: 12px; color: var(--muted); font-weight: 600; padding: 10px 8px; border-bottom: 1px solid var(--border); }
        table.simple td { padding: 12px 8px; border-bottom: 1px solid var(--border); font-size: 13.5px; }
        table.simple tr:last-child td { border-bottom: none; }
        table.simple tbody tr { transition: background .12s; }
        table.simple tbody tr:hover { background: color-mix(in srgb, var(--accent) 5%, transparent); }
        table.simple a { color: var(--accent-text); text-decoration: none; font-weight: 700; }

        .empty { text-align: center; padding: 46px 20px; color: var(--muted); }
        .empty svg { width: 46px; height: 46px; opacity: .4; margin-bottom: 10px; }

        @media (max-width: 680px) {
            .portal-header { flex-direction: column; align-items: stretch; padding: 12px 16px; gap: 10px; }
            .portal-header__nav { justify-content: center; }
            .portal-header__user { justify-content: space-between; margin: 0; }
            .col-hide-mobile { display: none; }
        }
    </style>
</head>
<body>

    <header class="portal-header">
        <div class="portal-header__brand">
            <img src="{{ \App\Support\Branding::logo('mark') }}" alt="{{ \App\Support\Branding::companyName() }}" onerror="this.style.display='none'">
            <span>{{ \App\Support\Branding::companyName() }}</span>
        </div>
        @auth
        <nav class="portal-header__nav">
            <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">{{ __('portal.home') }}</a>
            <a href="{{ route('portal.tickets.index') }}" class="{{ request()->routeIs('portal.tickets.*') ? 'active' : '' }}">{{ __('portal.my_tickets') }}</a>
            @if (auth()->user()->canViewInvoices())
                <a href="{{ route('portal.invoices.index') }}" class="{{ request()->routeIs('portal.invoices.*') ? 'active' : '' }}">{{ __('portal.my_invoices') }}</a>
            @endif
        </nav>
        <div class="portal-header__user">
            <span class="portal-header__chip">
                <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                {{ auth()->user()->name }}
            </span>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit">{{ __('auth.logout') }}</button>
            </form>
        </div>
        @endauth
    </header>

    <main class="portal-main">
        @if (session('status'))
            <div class="status-banner success">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </main>

    <x-footer />

</body>
</html>
