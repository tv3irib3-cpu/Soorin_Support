<x-layouts.portal :title="__('portal.my_tickets')">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h2 style="margin:0;">{{ __('portal.my_tickets') }}</h2>
        @if (auth()->user()->canCreateTicket())
            <a href="{{ route('portal.tickets.create') }}" class="btn">{{ __('portal.new_ticket') }}</a>
        @endif
    </div>

    <div class="card" style="padding:0;">
        @if ($tickets->isEmpty())
            <div class="empty">{{ __('tickets.empty_portal') }}</div>
        @else
            <table class="simple">
                <thead>
                    <tr>
                        <th>{{ __('tickets.number') }}</th>
                        <th>{{ __('tickets.subject') }}</th>
                        <th class="col-hide-mobile">{{ __('tickets.category') }}</th>
                        <th>{{ __('tickets.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                    <tr>
                        <td style="font-family:monospace;">{{ $ticket->number }}</td>
                        <td><a href="{{ route('portal.tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                        <td class="col-hide-mobile">{{ $ticket->category?->name ?? '—' }}</td>
                        <td><span class="badge gray">{{ __('tickets.statuses.' . $ticket->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div style="margin-top:14px;">{{ $tickets->links() }}</div>
</x-layouts.portal>
