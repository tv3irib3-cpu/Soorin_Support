{{--
    گفتگوی تیکت به‌صورتِ حباب‌های چت در پنلِ پشتیبان.
    از دیدِ پشتیبان: پیامِ پشتیبان سمتِ راست (خودی)، پیامِ مشتری سمتِ چپ.
    یادداشتِ داخلی با نشانِ زرد مشخص می‌شود و هرگز به مشتری نشان داده نمی‌شود.
--}}
@php
    /** @var \App\Models\Ticket $record */
    $record   = $getRecord();
    $messages = $record->messages()->with('user')->orderBy('created_at')->get();
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
</style>

@if ($messages->isEmpty())
    <div class="tk-empty">{{ __('tickets.no_messages') }}</div>
@else
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
            </div>
        @endforeach
    </div>
@endif
