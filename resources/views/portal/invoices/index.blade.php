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
                    <tr>
                        <td style="font-family:monospace;">{{ $invoice->number }}</td>
                        <td class="col-hide-mobile">{{ \App\Support\Jalali::format($invoice->issue_date) }}</td>
                        <td>{{ \App\Support\Jalali::money($invoice->payable_amount) }} {{ __('common.currency') }}</td>
                        <td><span class="badge gray">{{ __('invoices.statuses.' . $invoice->status) }}</span></td>
                        <td>
                            <a href="{{ route('invoices.pdf.view', $invoice) }}" target="_blank">{{ __('invoices.pdf') }}</a>
                            @if ($canPrint)
                                · <a href="{{ route('invoices.pdf.download', $invoice) }}">{{ __('invoices.print') }}</a>
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
