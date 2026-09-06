<x-layouts.portal :title="$ticket->number">
    @php
        $badge = match ($ticket->status) {
            'new', 'waiting_customer', 'waiting_support', 'waiting_payment' => 'warning',
            'in_progress', 'resolved'                                       => 'success',
            default                                                         => 'gray',
        };
        $canReply = $ticket->canReceiveMessages();
    @endphp

    <style>
        .chat-attachments { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .chat-img { max-width: 220px; max-height: 180px; border-radius: 10px; border: 1px solid var(--border); display: block; }
        .attach-chip {
            display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
            background: var(--bg); border: 1px solid var(--border); color: var(--text);
            padding: 8px 12px; border-radius: 10px; font-size: 12.5px; font-weight: 600;
        }
        .attach-chip:hover { border-color: var(--accent); }
        .attach-chip svg { width: 16px; height: 16px; color: var(--accent-text); }
        .msg-count {
            display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;
            background: var(--accent-soft); color: var(--accent-text); padding: 4px 11px; border-radius: 999px;
        }
        .file-field input[type=file] { width: 100%; font-family: inherit; font-size: 13px; padding: 8px; border: 1px dashed var(--border); border-radius: 10px; background: var(--bg); color: var(--muted); }
        .file-hint { font-size: 11.5px; color: var(--muted); margin-top: 5px; }
    </style>

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

            @if ($ticketAttachments->isNotEmpty())
                <div class="chat-attachments">
                    @foreach ($ticketAttachments as $att)
                        <x-ticket-attachment :attachment="$att" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="page-head" style="margin-top:22px; margin-bottom:12px;">
        <h3 style="margin:0;">{{ __('tickets.conversation') }}</h3>
        <span class="msg-count">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            {{ \App\Support\Jalali::digits((string) $ticket->publicMessages->count()) }} {{ __('tickets.messages_count') }}
        </span>
    </div>

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
                        @if ($message->attachments->isNotEmpty())
                            <div class="chat-attachments">
                                @foreach ($message->attachments as $att)
                                    <x-ticket-attachment :attachment="$att" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if (! $canReply)
        <div class="status-banner warning">{{ __('portal.ticket_resolved_notice') }}</div>
    @else
        <div class="card">
            <form method="POST" action="{{ route('portal.tickets.reply', $ticket) }}" enctype="multipart/form-data">
                @csrf
                <div class="field" style="margin-bottom:12px;">
                    <textarea name="body" rows="3" placeholder="{{ __('portal.reply_placeholder') }}" required>{{ old('body') }}</textarea>
                </div>
                <div class="field file-field" style="margin-bottom:12px;">
                    <input type="file" name="attachments[]" multiple accept="image/*,video/*,.pdf">
                    <div class="file-hint">{{ __('tickets.attach_hint') }}</div>
                </div>
                @error('attachments.*')<div class="field"><div class="error">{{ $message }}</div></div>@enderror
                <button type="submit" class="btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    {{ __('tickets.reply') }}
                </button>
            </form>
        </div>
    @endif
</x-layouts.portal>
