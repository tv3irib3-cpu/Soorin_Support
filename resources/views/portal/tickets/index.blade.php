<x-layouts.portal :title="__('portal.my_tickets')">
    @php
        $badge = fn (string $s) => match ($s) {
            'new', 'waiting_customer', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                    => 'success',
            default                                      => 'gray',
        };
    @endphp

    <div class="page-head">
        <h1>{{ __('portal.my_tickets') }}</h1>
        @if (auth()->user()->canCreateTicket())
            <a href="{{ route('portal.tickets.create') }}" class="btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('portal.new_ticket') }}
            </a>
        @endif
    </div>

    <div class="card" style="padding:0;">
        @if ($tickets->isEmpty())
            <div class="empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <div>{{ __('tickets.empty_portal') }}</div>
            </div>
        @else
            <table class="simple">
                <thead>
                    <tr>
                        <th>{{ __('tickets.number') }}</th>
                        <th>{{ __('tickets.subject') }}</th>
                        <th class="col-hide-mobile">{{ __('tickets.category') }}</th>
                        <th class="col-hide-mobile">{{ __('tickets.date') }}</th>
                        <th>{{ __('tickets.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                    <tr>
                        <td style="font-family:monospace;" dir="ltr">{{ $ticket->number }}</td>
                        <td><a href="{{ route('portal.tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                        <td class="col-hide-mobile">{{ $ticket->category?->name ?? '—' }}</td>
                        <td class="col-hide-mobile">{{ \App\Support\Jalali::format($ticket->created_at) }}</td>
                        <td><span class="badge {{ $badge($ticket->status) }}">{{ __('tickets.statuses.' . $ticket->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div style="margin-top:14px;">{{ $tickets->links() }}</div>
</x-layouts.portal>
