<x-layouts.portal :title="__('portal.my_invoices')">
    <h2 style="margin-top:0;">{{ __('portal.my_invoices') }}</h2>

    <div class="card" style="padding:0;">
        @if ($invoices->isEmpty())
            <div class="empty">{{ __('invoices.empty_heading') }}</div>
        @else
            <table class="simple">
                <thead>
                    <tr>
                        <th>{{ __('invoices.number') }}</th>
                        <th class="col-hide-mobile">{{ __('invoices.issue_date') }}</th>
                        <th>{{ __('invoices.payable_amount') }}</th>
                        <th>{{ __('invoices.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                    @php
                        $__cancelled = $invoice->status === \App\Models\Invoice::STATUS_CANCELLED;
                        // فاکتورِ جدید: قابل‌دیدن (نه پیش‌نویس/لغوشده) و پس از آخرین بازدیدِ کاربر صادر شده.
                        $__new = ! $__cancelled
                            && $invoice->issued_at
                            && in_array($invoice->status, \App\Models\Invoice::VISIBLE_STATUSES, true)
                            && (empty($lastSeen) || $invoice->issued_at->gt($lastSeen));
                    @endphp
                    <tr @class(['row-new' => $__new])>
                        <td style="font-family:monospace;">
                            {{ $invoice->number }}
                            @if ($__new)
                                <span class="new-pill">{{ __('portal.new_badge') }}</span>
                            @endif
                        </td>
                        <td class="col-hide-mobile">{{ \App\Support\Jalali::format($invoice->issue_date) }}</td>
                        <td>{{ \App\Support\Jalali::money($invoice->payable_amount) }} {{ __('common.currency') }}</td>
                        <td><span class="badge {{ $__cancelled ? 'gray' : 'success' }}">{{ __('invoices.statuses.' . $invoice->status) }}</span></td>
                        <td>
                            @if ($__cancelled)
                                {{-- فاکتورِ لغوشده فقط وضعیت را نشان می‌دهد و باز نمی‌شود --}}
                                <span style="color:var(--muted); font-size:12px;">—</span>
                            @else
                                <a href="{{ route('invoices.pdf.view', $invoice) }}" target="_blank">{{ __('invoices.pdf') }}</a>
                                @if ($canPrint)
                                    · <a href="{{ route('invoices.pdf.download', $invoice) }}">{{ __('invoices.print') }}</a>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div style="margin-top:14px;">{{ $invoices->links() }}</div>
</x-layouts.portal>
