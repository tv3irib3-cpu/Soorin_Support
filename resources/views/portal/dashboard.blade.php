<x-layouts.portal>
    @php
        $user = auth()->user();
        $badge = fn (string $s) => match ($s) {
            'new', 'waiting_customer', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                    => 'success',
            default                                      => 'gray',
        };
    @endphp

    <div class="page-head">
        <div>
            <h1>{{ __('portal.welcome', ['name' => $user->name]) }}</h1>
            <div class="sub">{{ __('portal.title') }} — {{ \App\Support\Branding::companyName() }}</div>
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

    {{-- کارت‌های آمار --}}
    <div class="stat-grid">
        <div class="stat">
            <span class="stat__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $openTickets) }}</div>
                <div class="stat__label">{{ __('portal.open_tickets') }}</div>
            </div>
        </div>
        <div class="stat">
            <span class="stat__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $closedTickets) }}</div>
                <div class="stat__label">{{ __('portal.closed_tickets') }}</div>
            </div>
        </div>
        @if ($user->canViewInvoices())
        <div class="stat">
            <span class="stat__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
            </span>
            <div>
                <div class="stat__num">{{ \App\Support\Jalali::digits((string) $unpaidInvoices) }}</div>
                <div class="stat__label">{{ __('portal.unpaid_invoices') }}</div>
            </div>
        </div>
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
                    <tr>
                        <td style="font-family:monospace;" dir="ltr">{{ $ticket->number }}</td>
                        <td><a href="{{ route('portal.tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                        <td class="col-hide-mobile">{{ \App\Support\Jalali::format($ticket->created_at) }}</td>
                        <td><span class="badge {{ $badge($ticket->status) }}">{{ __('tickets.statuses.' . $ticket->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.portal>
