<x-layouts.portal :title="__('portal.my_tickets')">
    @php
        $badge = fn (string $s) => match ($s) {
            'new', 'waiting_customer', 'waiting_support', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                                       => 'success',
            default                                                         => 'gray',
        };
    @endphp

    <style>
        .filter-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 14px; }
        .filter-bar__label { font-size: 13px; font-weight: 700; color: var(--muted); }
        .filter-chip {
            display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
            font-size: 12.5px; padding: 5px 11px; border-radius: 999px;
            border: 1px solid var(--border); background: var(--card); color: var(--text);
            user-select: none;
        }
        .filter-chip input { accent-color: var(--accent); width: 14px; height: 14px; }
        .filter-chip.on { border-color: var(--accent); background: var(--accent-soft); color: var(--accent-text); font-weight: 700; }
        .filter-clear { font-size: 12.5px; color: var(--muted); text-decoration: none; }
        .filter-clear:hover { color: var(--accent-text); text-decoration: underline; }
        .btn.sm { padding: 6px 12px; font-size: 12.5px; }
    </style>

    <div class="page-head">
        <h1>{{ __('portal.my_tickets') }}</h1>
        @if (auth()->user()->canCreateTicket())
            <a href="{{ route('portal.tickets.create') }}" class="btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('portal.new_ticket') }}
            </a>
        @endif
    </div>

    {{-- فیلترِ چندانتخابیِ وضعیت --}}
    <form method="GET" action="{{ route('portal.tickets.index') }}" class="filter-bar">
        <span class="filter-bar__label">{{ __('portal.filter_status') }}</span>
        @foreach (__('tickets.statuses') as $key => $label)
            <label class="filter-chip @if (in_array($key, $statusFilter, true)) on @endif">
                <input type="checkbox" name="status[]" value="{{ $key }}" @checked(in_array($key, $statusFilter, true))>
                {{ $label }}
            </label>
        @endforeach
        <button type="submit" class="btn secondary sm">{{ __('portal.apply_filter') }}</button>
        @if ($statusFilter)
            <a href="{{ route('portal.tickets.index') }}" class="filter-clear">{{ __('portal.clear_filter') }}</a>
        @endif
    </form>

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
                        <th class="col-hide-mobile">{{ __('tickets.creator') }}</th>
                        <th class="col-hide-mobile">{{ __('tickets.category') }}</th>
                        <th class="col-hide-mobile">{{ __('tickets.date') }}</th>
                        <th>{{ __('tickets.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                    @php
                        $__url = route('portal.tickets.show', $ticket);
                        $__unread = $unread[$ticket->id] ?? 0;
                    @endphp
                    <tr onclick="window.location='{{ $__url }}'" style="cursor:pointer;"
                        class="ticket-row ticket-row-{{ $ticket->priority }}@if ($ticket->isCreatedBySupport()) ticket-row-outgoing @endif"
                        @class(['row-unread' => $__unread > 0])>
                        <td style="font-family:monospace;" dir="ltr"><a href="{{ $__url }}">{{ $ticket->number }}</a></td>
                        <td>
                            <a href="{{ $__url }}" @style(['font-weight:700' => $__unread > 0])>{{ $ticket->subject }}</a>
                            @if ($__unread > 0)
                                <span class="unread-pill" title="{{ __('portal.unread_messages') }}">
                                    {{ \App\Support\Jalali::digits((string) $__unread) }}
                                    {{ __('portal.unread_new') }}
                                </span>
                            @endif
                        </td>
                        <td class="col-hide-mobile">
                            {{ $ticket->creator?->name ?? '—' }}
                            @if ($ticket->isCreatedBySupport())
                                <span class="soorin-badge-support" title="{{ __('tickets.by_support_hint') }}">{{ __('tickets.by_support') }}</span>
                            @endif
                        </td>
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
