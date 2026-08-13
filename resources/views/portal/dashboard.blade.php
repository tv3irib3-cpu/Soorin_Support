<x-layouts.portal>
    @php $user = auth()->user(); @endphp

    <h2 style="margin-top:0;">{{ __('portal.welcome', ['name' => $user->name]) }}</h2>

    @if ($user->customer && ! $user->customer->canReceiveService())
        <div class="status-banner warning">{{ $user->customer->suspensionNotice() }}</div>
    @endif

    <div class="card" style="display:flex; gap:24px; flex-wrap:wrap;">
        <div>
            <div style="font-size:22px; font-weight:700;">{{ \App\Support\Jalali::digits((string) $openTickets) }}</div>
            <div style="color:var(--muted); font-size:13px;">{{ __('portal.open_tickets') }}</div>
        </div>
        <div>
            <div style="font-size:22px; font-weight:700;">{{ \App\Support\Jalali::digits((string) $closedTickets) }}</div>
            <div style="color:var(--muted); font-size:13px;">{{ __('portal.closed_tickets') }}</div>
        </div>
        @if ($user->canViewInvoices())
        <div>
            <div style="font-size:22px; font-weight:700;">{{ \App\Support\Jalali::digits((string) $unpaidInvoices) }}</div>
            <div style="color:var(--muted); font-size:13px;">{{ __('portal.unpaid_invoices') }}</div>
        </div>
        @endif
    </div>

    <div class="card" style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
        @if ($user->canCreateTicket())
            <a href="{{ route('portal.tickets.create') }}" class="btn">{{ __('portal.new_ticket') }}</a>
        @endif
        <a href="{{ route('portal.tickets.index') }}" class="btn secondary">{{ __('portal.my_tickets') }}</a>
        @if ($user->canViewInvoices())
            <a href="{{ route('portal.invoices.index') }}" class="btn secondary">{{ __('portal.my_invoices') }}</a>
        @endif
    </div>
</x-layouts.portal>
