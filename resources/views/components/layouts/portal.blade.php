<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="{{ $activeTheme ?? config('branding.default_theme') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('portal.title') }} — {{ config('branding.company.name') }}</title>
    <x-favicon />
    <link rel="stylesheet" href="{{ route('theme.css') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Vazirmatn, system-ui, sans-serif; }

        .portal-header {
            background: var(--nav); color: var(--nav-on);
            padding: 14px 22px; display: flex; align-items: center; justify-content: space-between;
        }
        .portal-header__brand { display: flex; align-items: center; gap: 10px; font-weight: 700; }
        .portal-header__brand img { height: 32px; }
        .portal-header__nav { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .portal-header__nav a { color: var(--nav-text); text-decoration: none; font-size: 13.5px; }
        .portal-header__nav a:hover, .portal-header__nav a.active { color: var(--nav-active); }
        .portal-header__nav form button {
            background: none; border: none; color: var(--nav-text); font-family: inherit;
            font-size: 13.5px; cursor: pointer; padding: 0;
        }

        .portal-main { max-width: 960px; margin: 28px auto; padding: 0 18px; min-height: 60vh; }

        .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; box-shadow: var(--shadow); }
        .card + .card { margin-top: 16px; }

        .btn {
            display: inline-block; background: var(--accent); color: #fff; border: none;
            border-radius: 8px; padding: 9px 18px; font-family: inherit; font-size: 13.5px;
            cursor: pointer; text-decoration: none;
        }
        .btn.secondary { background: transparent; color: var(--text); border: 1px solid var(--border); }

        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 5px; font-size: 13px; color: var(--muted); }
        .field input, .field select, .field textarea {
            width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px;
            font-family: inherit; font-size: 14px; background: var(--card); color: var(--text);
        }
        .field .error { color: var(--danger); font-size: 12px; margin-top: 4px; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11.5px; }
        .badge.success { background: var(--accent-soft); color: var(--accent-text); }
        .badge.warning { background: #fef3c7; color: var(--warning); }
        .badge.gray { background: var(--border); color: var(--muted); }

        .status-banner { padding: 12px 16px; border-radius: var(--radius); margin-bottom: 16px; font-size: 13.5px; }
        .status-banner.success { background: var(--accent-soft); color: var(--accent-text); }
        .status-banner.warning { background: #fef3c7; color: #92400e; }

        table.simple { width: 100%; border-collapse: collapse; }
        table.simple th { text-align: right; font-size: 12.5px; color: var(--muted); padding: 8px; border-bottom: 1px solid var(--border); }
        table.simple td { padding: 10px 8px; border-bottom: 1px solid var(--border); font-size: 13.5px; }
        table.simple tr:last-child td { border-bottom: none; }
        table.simple a { color: var(--accent-text); text-decoration: none; font-weight: 600; }

        .empty { text-align: center; padding: 40px 20px; color: var(--muted); }

        @media (max-width: 640px) {
            .portal-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .col-hide-mobile { display: none; }
        }
    </style>
</head>
<body>

    <header class="portal-header">
        <div class="portal-header__brand">
            <img src="{{ asset(config('branding.logo.mark')) }}" alt="{{ config('branding.company.name') }}" onerror="this.style.display='none'">
            {{ __('portal.title') }}
        </div>
        @auth
        <nav class="portal-header__nav">
            <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'active' : '' }}">{{ __('portal.home') }}</a>
            <a href="{{ route('portal.tickets.index') }}" class="{{ request()->routeIs('portal.tickets.*') ? 'active' : '' }}">{{ __('portal.my_tickets') }}</a>
            @if (auth()->user()->canViewInvoices())
                <a href="{{ route('portal.invoices.index') }}" class="{{ request()->routeIs('portal.invoices.*') ? 'active' : '' }}">{{ __('portal.my_invoices') }}</a>
            @endif
            <span>{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit">{{ __('auth.logout') }}</button>
            </form>
        </nav>
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
