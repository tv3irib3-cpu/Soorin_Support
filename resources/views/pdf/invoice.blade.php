{{-- خروجی PDF فاکتور — راست‌چین، فونت وزیرمتن. mPDF از CSS محدودی پشتیبانی می‌کند؛ عمداً از جدول برای چیدمان استفاده شده است. --}}
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<style>
    body { font-family: vazirmatn; font-size: 11pt; color: #0b2b3f; direction: rtl; }
    table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: middle; }
    .company-name { font-size: 14pt; font-weight: bold; color: #0f2d4d; }
    .company-sub { font-size: 9pt; color: #5f7d8c; }
    .invoice-title { font-size: 16pt; font-weight: bold; color: #0f766e; text-align: left; }
    .invoice-meta { font-size: 9.5pt; color: #5f7d8c; text-align: left; }
    .divider { border-top: 2px solid #0f2d4d; margin: 8px 0 14px; }
    .info-table td { padding: 3px 0; font-size: 10pt; }
    .info-label { color: #5f7d8c; width: 90px; }
    .items-table { margin-top: 14px; }
    .items-table th { background: #0f2d4d; color: #fff; padding: 6px 8px; font-size: 9.5pt; text-align: right; }
    .items-table td { padding: 6px 8px; font-size: 9.5pt; border-bottom: 1px solid #dde8ec; }
    .items-table .num { text-align: left; direction: ltr; }
    .summary-table { margin-top: 14px; width: 55%; margin-inline-start: auto; }
    .summary-table td { padding: 5px 8px; font-size: 10pt; }
    .summary-table .label { color: #5f7d8c; }
    .summary-table .value { text-align: left; direction: ltr; font-weight: bold; }
    .summary-table .payable-row { background: #ccfbf1; }
    .summary-table .payable-row .value { color: #0f766e; font-size: 12pt; }
    .warranty-badge { display: inline-block; background: #ccfbf1; color: #0f766e; padding: 2px 10px; border-radius: 10px; font-size: 9pt; }
    .payments-table th { background: #eef4f6; padding: 5px 8px; font-size: 9pt; text-align: right; }
    .payments-table td { padding: 5px 8px; font-size: 9pt; border-bottom: 1px solid #dde8ec; }
    .footer-note { margin-top: 24px; font-size: 8.5pt; color: #5f7d8c; text-align: center; }
    .section-title { font-size: 11pt; font-weight: bold; color: #0f2d4d; margin-top: 16px; margin-bottom: 6px; }
</style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @php $logoPath = public_path('images/logo-navy.png'); @endphp
                @if (file_exists($logoPath))
                    <img src="{{ $logoPath }}" style="height: 42px;">
                @else
                    <div class="company-name">{{ $company['name'] }}</div>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="invoice-title">{{ __('invoices.invoice_title') }}</div>
                <div class="invoice-meta">{{ __('invoices.number') }}: {{ $invoice->number }}</div>
                <div class="invoice-meta">{{ __('invoices.issue_date') }}: {{ $date($invoice->issue_date) }}</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="info-table">
        <tr>
            <td class="info-label">{{ __('invoices.issued_for') }}</td>
            <td><strong>{{ $invoice->customer->name }}</strong></td>
            <td class="info-label">{{ __('customers.code') }}</td>
            <td>{{ $invoice->customer->code }}</td>
        </tr>
        @if ($invoice->ticket)
        <tr>
            <td class="info-label">{{ __('tickets.label') }}</td>
            <td>{{ $invoice->ticket->number }}</td>
            <td class="info-label">{{ __('tickets.subject') }}</td>
            <td>{{ $invoice->ticket->subject }}</td>
        </tr>
        @endif
        @if ($invoice->contract)
        <tr>
            <td class="info-label">{{ __('contracts.label') }}</td>
            <td>{{ $invoice->contract->number }} ({{ $invoice->contract->plan?->name }})</td>
            <td></td><td></td>
        </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">{{ __('invoices.item_title') }}</th>
                <th>{{ __('invoices.quantity') }}</th>
                <th>{{ __('invoices.unit_price') }}</th>
                <th>{{ __('invoices.cover_percent') }}</th>
                <th>{{ __('invoices.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->title }}</td>
                <td class="num">{{ \App\Support\Jalali::digits(rtrim(rtrim($item->quantity, '0'), '.') ?: '0') }}</td>
                <td class="num">{{ $money($item->unit_price) }}</td>
                <td class="num">{{ \App\Support\Jalali::digits((string) $item->contract_cover_percent) }}٪</td>
                <td class="num">{{ $money($item->line_total) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="label">{{ __('invoices.service_amount') }}</td>
            <td class="value">{{ $money($invoice->service_amount) }} {{ __('common.currency') }}</td>
        </tr>
        @if ($invoice->parts_amount > 0)
        <tr>
            <td class="label">{{ __('invoices.parts_amount') }}</td>
            <td class="value">{{ $money($invoice->parts_amount) }} {{ __('common.currency') }}</td>
        </tr>
        @endif
        @if ($invoice->discount_amount > 0)
        <tr>
            <td class="label">{{ __('invoices.discount_amount') }}</td>
            <td class="value">-{{ $money($invoice->discount_amount) }} {{ __('common.currency') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">{{ __('invoices.contract_amount') }}</td>
            <td class="value">{{ $money($invoice->contract_amount) }} {{ __('common.currency') }}</td>
        </tr>
        <tr class="payable-row">
            <td class="label"><strong>{{ __('invoices.payable_amount') }}</strong></td>
            <td class="value">{{ $money($invoice->payable_amount) }} {{ __('common.currency') }}</td>
        </tr>
    </table>

    @if ($invoice->is_warranty)
        <div style="margin-top: 8px; text-align: left;">
            <span class="warranty-badge">{{ __('invoices.is_warranty_badge') }}</span>
        </div>
    @endif

    @if ($invoice->payments->isNotEmpty())
        <div class="section-title">{{ __('invoices.payments') }}</div>
        <table class="payments-table">
            <thead>
                <tr>
                    <th>{{ __('invoices.paid_at') }}</th>
                    <th>{{ __('invoices.method') }}</th>
                    <th>{{ __('invoices.reference') }}</th>
                    <th>{{ __('invoices.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $payment)
                <tr>
                    <td>{{ $date($payment->paid_at) }}</td>
                    <td>{{ __('invoices.methods.' . $payment->method) }}</td>
                    <td>{{ $payment->reference ?: '—' }}</td>
                    <td class="num">{{ $money($payment->amount) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer-note">
        {{ __('invoices.not_official') }}<br>
        {{ __('invoices.thanks') }} — {{ $company['name'] }} · {{ $company['website_label'] }}
    </div>

</body>
</html>
