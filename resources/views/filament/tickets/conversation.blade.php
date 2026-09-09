{{--
    گفتگوی تیکت به‌صورتِ حباب‌های چت در پنلِ پشتیبان.
    از دیدِ پشتیبان: پیامِ پشتیبان سمتِ راست (خودی)، پیامِ مشتری سمتِ چپ.
    یادداشتِ داخلی با نشانِ زرد مشخص می‌شود و هرگز به مشتری نشان داده نمی‌شود.
--}}
@php
    /** @var \App\Models\Ticket $record */
    $record   = $getRecord();
    $messages = $record->messages()->with(['user', 'attachments'])->orderBy('created_at')->get();
@endphp

<style>
    .tk-thread { display: flex; flex-direction: column; gap: 12px; }
    .tk-msg { max-width: 78%; padding: 10px 14px; border-radius: 14px; font-size: 13.5px; line-height: 1.8; border: 1px solid var(--gray-200, #e5e7eb); }
    .dark .tk-msg { border-color: var(--gray-700, #374151); }
    .tk-msg__meta { font-size: 11px; opacity: .7; margin-bottom: 4px; display: flex; gap: 8px; align-items: center; }
    .tk-msg--support { align-self: flex-end; background: rgba(16,185,129,.12); border-bottom-left-radius: 4px; }
    .tk-msg--customer { align-self: flex-start; background: rgba(148,163,184,.14); border-bottom-right-radius: 4px; }
    .tk-msg--internal { background: rgba(245,158,11,.14); border-color: rgba(245,158,11,.4); align-self: stretch; max-width: 100%; }
    .tk-msg__body { white-space: pre-wrap; word-break: break-word; }
    .tk-internal-tag { font-size: 10.5px; font-weight: 700; color: #b45309; background: rgba(245,158,11,.2); padding: 1px 7px; border-radius: 999px; }
    .tk-empty { text-align: center; padding: 28px; opacity: .55; font-size: 13px; }
    .tk-count { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; background: rgba(16,185,129,.14); color: #0f766e; padding: 4px 12px; border-radius: 999px; margin-bottom: 12px; }
    .dark .tk-count { color: #6ee7b7; }
    .tk-atts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .tk-att-img { max-width: 200px; max-height: 160px; border-radius: 8px; border: 1px solid rgba(148,163,184,.4); display: block; }
    .tk-att-chip { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; background: rgba(148,163,184,.14); border: 1px solid rgba(148,163,184,.4); color: inherit; padding: 7px 11px; border-radius: 8px; font-size: 12px; font-weight: 600; }
</style>

@if ($messages->isEmpty())
    <div class="tk-empty">{{ __('tickets.no_messages') }}</div>
@else
    <div class="tk-count">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        {{ \App\Support\Jalali::digits((string) $messages->count()) }} {{ __('tickets.messages_count') }}
    </div>
    <div class="tk-thread">
        @foreach ($messages as $message)
            @php
                $isSupport  = $message->user?->isSupportUser() ?? false;
                $isInternal = (bool) $message->is_internal;
                $cls = $isInternal ? 'tk-msg--internal' : ($isSupport ? 'tk-msg--support' : 'tk-msg--customer');
            @endphp
            <div class="tk-msg {{ $cls }}">
                <div class="tk-msg__meta">
                    <span>{{ $message->user?->name ?? __('customers.label') }}</span>
                    <span>·</span>
                    <span>{{ \App\Support\Jalali::formatDateTime($message->created_at) }}</span>
                    @if ($isInternal)
                        <span class="tk-internal-tag">{{ __('tickets.internal_note') }}</span>
                    @endif
                </div>
                <div class="tk-msg__body">{{ $message->body }}</div>
                @if ($message->attachments->isNotEmpty())
                    <div class="tk-atts">
                        @foreach ($message->attachments as $att)
                            @php
                                $isImg = $att->isImage();
                                $isVid = $att->isVideo();
                                $view  = route('ticket-attachments.download', $att) . '?view=1';
                                $dl    = route('ticket-attachments.download', $att);
                            @endphp
                            @if ($isImg)
                                <a href="{{ $view }}" target="_blank" rel="noopener"><img class="tk-att-img" src="{{ $view }}" alt="{{ $att->original_name }}" loading="lazy"></a>
                            @elseif ($isVid)
                                <video class="tk-att-img" controls preload="metadata" src="{{ $view }}"></video>
                            @else
                                <a class="tk-att-chip" href="{{ $dl }}" target="_blank" rel="noopener">📎 {{ $att->original_name }} <span style="opacity:.6">({{ $att->humanSize() }})</span></a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
