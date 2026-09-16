<x-layouts.portal>
    @php
        $user = auth()->user();
        $badge = fn (string $s) => match ($s) {
            'new', 'waiting_customer', 'waiting_support', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                                       => 'success',
            default                                                         => 'gray',
        };
    @endphp

    <style>
        a.stat { text-decoration: none; color: inherit; transition: transform .08s, box-shadow .15s, border-color .15s; }
        a.stat:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(0,0,0,.09); border-color: var(--accent); }
        .stat__icon.danger { background: color-mix(in srgb, #ef4444 16%, transparent); color: #ef4444; }
    </style>

    <div class="page-head">
        <div style="display:flex; align-items:center; gap:14px;">
            @php $__logo = $user->customer?->logoData(); @endphp
            @if ($__logo)
                <img src="{{ $__logo }}" alt="{{ $user->customer->name }}"
                     style="height:56px; width:auto; max-width:150px; object-fit:contain; border-radius:10px; background:#fff; padding:5px 8px; border:1px solid var(--border); flex:none;">
            @endif
            <div>
                <h1>{{ __('portal.welcome', ['name' => $user->name]) }}</h1>
                <div class="sub">
                    @if ($user->customer){{ $user->customer->name }}@endif
                </div>
            </div>
        </div>
        @if ($user->canCreateTicket())
            <a href="{{ route('portal.tickets.create') }}" class="btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('portal.new_ticket') }}
            </a>
        @endif
    </div>

    @if ($user->customer && ! $user->customer->canReceiveService())
        <div class="status-banner warning">{{ $user->customer->suspensionNotice() }}</div>
    @elseif (! $user->canCreateTicket())
        {{-- سرویس فعال است ولی ثبتِ تیکت برای این حساب خاموش است — علت را شفاف بگو --}}
        <div class="status-banner warning">{{ __('portal.no_access_new_ticket') }}</div>
    @endif

    {{-- کارت‌های آمار — کلیک‌پذیر --}}
    <div class="stat-grid">
        @if ($unreadCount > 0)
        <a class="stat" href="{{ route('portal.tickets.index') }}">
            <span class="stat__icon danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $unreadCount) }}</div>
                <div class="stat__label">{{ __('portal.unread_messages') }}</div>
            </div>
        </a>
        @endif
        @if (($resolvedUnrated ?? 0) > 0)
        <a class="stat" href="{{ route('portal.tickets.index') }}">
            <span class="stat__icon" style="background: color-mix(in srgb, #f59e0b 16%, transparent); color:#f59e0b;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 15.09 8.26 22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $resolvedUnrated) }}</div>
                <div class="stat__label">{{ __('portal.resolved_unrated') }}</div>
            </div>
        </a>
        @endif
        {{-- نیازمندِ رسیدگی = همهٔ تیکت‌های فعال (منتظر پشتیبان/مشتری، در حال بررسی، منتظر پرداخت) --}}
        <a class="stat" href="{{ route('portal.tickets.index', ['status' => ['waiting_support', 'waiting_customer', 'in_progress', 'waiting_payment']]) }}">
            <span class="stat__icon @if ($needsAttention > 0) danger @endif">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $needsAttention) }}</div>
                <div class="stat__label">{{ __('dashboard.needs_attention') }}</div>
            </div>
        </a>
        {{-- حل‌شده --}}
        <a class="stat" href="{{ route('portal.tickets.index', ['status' => ['resolved']]) }}">
            <span class="stat__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $resolvedCount) }}</div>
                <div class="stat__label">{{ __('tickets.statuses.resolved') }}</div>
            </div>
        </a>
        @if ($user->canViewInvoices())
        <a class="stat" href="{{ route('portal.invoices.index') }}">
            <span class="stat__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $unpaidInvoices) }}</div>
                <div class="stat__label">{{ __('portal.unpaid_invoices') }}</div>
            </div>
        </a>
        @endif
    </div>

    {{-- تیکت‌های اخیر --}}
    <div class="card" style="margin-top:18px; padding:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border);">
            <strong>{{ __('portal.recent_tickets') }}</strong>
            <a href="{{ route('portal.tickets.index') }}" style="color:var(--accent-text); text-decoration:none; font-size:13px; font-weight:700;">{{ __('portal.view_all') }}</a>
        </div>

        @if ($recentTickets->isEmpty())
            <div class="empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <div>{{ __('tickets.empty_portal') }}</div>
                @if ($user->canCreateTicket())
                    <div style="margin-top:14px;"><a href="{{ route('portal.tickets.create') }}" class="btn">{{ __('portal.new_ticket') }}</a></div>
                @endif
            </div>
        @else
            <table class="simple">
                <thead>
                    <tr>
                        <th>{{ __('tickets.number') }}</th>
                        <th>{{ __('tickets.subject') }}</th>
                        <th class="col-hide-mobile">{{ __('tickets.date') }}</th>
                        <th>{{ __('tickets.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentTickets as $ticket)
                    @php $__url = route('portal.tickets.show', $ticket); @endphp
                    <tr onclick="window.location='{{ $__url }}'" style="cursor:pointer;">
                        <td style="font-family:monospace;" dir="ltr"><a href="{{ $__url }}">{{ $ticket->number }}</a></td>
                        <td><a href="{{ $__url }}">{{ $ticket->subject }}</a></td>
                        <td class="col-hide-mobile">{{ \App\Support\Jalali::format($ticket->created_at) }}</td>
                        <td><span class="badge {{ $badge($ticket->status) }}">{{ __('tickets.statuses.' . $ticket->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.portal>
