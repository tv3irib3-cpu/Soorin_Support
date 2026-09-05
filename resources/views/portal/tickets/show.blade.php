<x-layouts.portal :title="$ticket->number">
    @php
        $badge = match ($ticket->status) {
            'new', 'waiting_customer', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                    => 'success',
            default                                      => 'gray',
        };
    @endphp

    <div class="page-head">
        <div>
            <h1>{{ $ticket->subject }}</h1>
            <div class="sub" dir="ltr" style="text-align:right;">{{ $ticket->number }}</div>
        </div>
        <span class="badge {{ $badge }}">{{ __('tickets.statuses.' . $ticket->status) }}</span>
    </div>

    <div class="card">
        <dl class="meta-list">
            @if ($ticket->category)
                <dt>{{ __('tickets.category') }}</dt><dd>{{ $ticket->category->fullName() }}</dd>
            @endif
            @if ($ticket->project)
                <dt>{{ __('tickets.project') }}</dt><dd>{{ $ticket->project->name }}</dd>
            @endif
            <dt>{{ __('tickets.date') }}</dt><dd>{{ \App\Support\Jalali::formatDateTime($ticket->created_at) }}</dd>
        </dl>

        <div style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border);">
            <div style="color:var(--muted); font-size:12px; margin-bottom:6px;">{{ __('tickets.description') }}</div>
            <div style="line-height:1.8; white-space:pre-wrap;">{{ $ticket->description }}</div>
        </div>
    </div>

    <h3 style="margin:22px 0 12px;">{{ __('tickets.conversation') }}</h3>

    <div class="card">
        @if ($ticket->publicMessages->isEmpty())
            <div class="empty">{{ __('tickets.no_messages') }}</div>
        @else
            <div class="thread">
                @foreach ($ticket->publicMessages as $message)
                    @php $isSupport = $message->user?->isSupportUser() ?? true; @endphp
                    <div class="msg {{ $isSupport ? 'msg--them' : 'msg--us' }}">
                        <div class="msg__meta">
                            {{ $message->user?->name ?? __('customers.label') }} · {{ \App\Support\Jalali::formatDateTime($message->created_at) }}
                        </div>
                        <div style="white-space:pre-wrap;">{{ $message->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($ticket->is_locked)
        <div class="status-banner warning">{{ __('portal.ticket_closed_notice') }}</div>
    @else
        <div class="card">
            <form method="POST" action="{{ route('portal.tickets.reply', $ticket) }}">
                @csrf
                <div class="field" style="margin-bottom:12px;">
                    <textarea name="body" rows="3" placeholder="{{ __('portal.reply_placeholder') }}" required></textarea>
                </div>
                <button type="submit" class="btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    {{ __('tickets.reply') }}
                </button>
            </form>
        </div>
    @endif
</x-layouts.portal>
