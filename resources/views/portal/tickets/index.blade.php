<x-layouts.portal :title="__('portal.my_tickets')">
    @php
        $badge = fn (string $s) => match ($s) {
            'new', 'waiting_customer', 'waiting_support', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                                       => 'success',
            default                                                         => 'gray',
        };
    @endphp

    <style>
        .btn.sm { padding: 6px 12px; font-size: 12.5px; }
        .filter-clear { font-size: 12.5px; color: var(--muted); text-decoration: none; }
        .filter-clear:hover { color: var(--accent-text); text-decoration: underline; }
        /* فیلترِ کشویی — مثلِ دکمهٔ فیلترِ پنلِ پشتیبان: یک دکمه که پنلِ انتخاب را باز می‌کند. */
        .pfilter { position: relative; display: inline-block; margin-bottom: 14px; }
        .pfilter > summary {
            display: inline-flex; align-items: center; gap: 8px; cursor: pointer; list-style: none;
            padding: 8px 14px; border-radius: 10px; border: 1px solid var(--border);
            background: var(--card); color: var(--text); font-size: 13px; font-weight: 700;
        }
        .pfilter > summary::-webkit-details-marker { display: none; }
        .pfilter > summary svg { width: 16px; height: 16px; color: var(--muted); }
        .pfilter[open] > summary { border-color: var(--accent); }
        .pfilter__badge {
            display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 999px; background: var(--accent); color: #fff; font-size: 11px;
        }
        .pfilter__panel {
            position: absolute; z-index: 30; margin-top: 6px; inset-inline-start: 0;
            min-width: 230px; padding: 12px; border-radius: 12px;
            background: var(--card); border: 1px solid var(--border); box-shadow: 0 12px 32px rgba(0,0,0,.14);
        }
        .pfilter__title { font-size: 11.5px; font-weight: 700; color: var(--muted); margin-bottom: 8px; }
        .pfilter__opt {
            display: flex; align-items: center; gap: 8px; padding: 6px 4px; cursor: pointer;
            font-size: 13px; border-radius: 8px;
        }
        .pfilter__opt:hover { background: color-mix(in srgb, var(--accent) 8%, transparent); }
        .pfilter__opt input { accent-color: var(--accent); width: 15px; height: 15px; }
        .pfilter__actions { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); }
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

    {{-- فیلترِ کشویی وضعیت (چندانتخابی) — سبکِ دکمهٔ فیلترِ پنلِ پشتیبان --}}
    <details class="pfilter">
        <summary>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            {{ __('portal.filter_status') }}
            @if (count($statusFilter))
                <span class="pfilter__badge">{{ \App\Support\Jalali::digits((string) count($statusFilter)) }}</span>
            @endif
        </summary>
        <form method="GET" action="{{ route('portal.tickets.index') }}" class="pfilter__panel">
            <div class="pfilter__title">{{ __('tickets.status') }}</div>
            @foreach (__('tickets.statuses') as $key => $label)
                <label class="pfilter__opt">
                    <input type="checkbox" name="status[]" value="{{ $key }}" @checked(in_array($key, $statusFilter, true))>
                    {{ $label }}
                </label>
            @endforeach
            <div class="pfilter__actions">
                <button type="submit" class="btn sm">{{ __('portal.apply_filter') }}</button>
                @if ($statusFilter)
                    <a href="{{ route('portal.tickets.index') }}" class="filter-clear">{{ __('portal.clear_filter') }}</a>
                @endif
            </div>
        </form>
    </details>

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
                        <th class="col-hide-mobile">{{ __('tickets.project') }}</th>
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
                        <td class="col-hide-mobile">{{ $ticket->project?->name ?? '—' }}</td>
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
