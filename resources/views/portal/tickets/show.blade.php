<x-layouts.portal :title="$ticket->number">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <h2 style="margin:0;">{{ $ticket->subject }}</h2>
        <span class="badge gray">{{ __('tickets.statuses.' . $ticket->status) }}</span>
    </div>

    <div class="card">
        <table class="simple" style="margin-bottom:0;">
            <tr>
                <td style="width:120px; color:var(--muted);">{{ __('tickets.number') }}</td>
                <td style="font-family:monospace;">{{ $ticket->number }}</td>
            </tr>
            @if ($ticket->category)
            <tr>
                <td style="color:var(--muted);">{{ __('tickets.category') }}</td>
                <td>{{ $ticket->category->fullName() }}</td>
            </tr>
            @endif
            @if ($ticket->project)
            <tr>
                <td style="color:var(--muted);">{{ __('tickets.project') }}</td>
                <td>{{ $ticket->project->name }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="card">
        <div style="color:var(--muted); font-size:12px; margin-bottom:6px;">{{ __('tickets.description') }}</div>
        <div>{{ $ticket->description }}</div>
    </div>

    <h3>{{ __('tickets.conversation') }}</h3>

    <div class="card">
        @forelse ($ticket->publicMessages as $message)
            <div style="padding:10px 0; {{ ! $loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
                <div style="font-size:12px; color:var(--muted); margin-bottom:4px;">
                    {{ $message->user?->name ?? __('customers.label') }} —
                    {{ \App\Support\Jalali::formatDateTime($message->created_at) }}
                </div>
                <div>{{ $message->body }}</div>
            </div>
        @empty
            <div class="empty">{{ __('tickets.no_messages') }}</div>
        @endforelse
    </div>

    @if ($ticket->is_locked)
        <div class="status-banner warning">{{ __('portal.ticket_closed_notice') }}</div>
    @else
        <div class="card">
            <form method="POST" action="{{ route('portal.tickets.reply', $ticket) }}">
                @csrf
                <div class="field">
                    <textarea name="body" rows="3" placeholder="{{ __('portal.reply_placeholder') }}" required></textarea>
                </div>
                <button type="submit" class="btn">{{ __('tickets.reply') }}</button>
            </form>
        </div>
    @endif
</x-layouts.portal>
