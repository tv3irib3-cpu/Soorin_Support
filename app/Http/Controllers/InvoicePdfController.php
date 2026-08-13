<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * نمایش و دانلود PDF فاکتور.
 *
 * این کنترلر هم از پنل مدیریت استفاده می‌شود و هم از پرتال مشتری —
 * پس مجوز اینجا بررسی می‌شود، نه فقط در لایه‌ای که لینک را نشان می‌دهد.
 */
class InvoicePdfController extends Controller
{
    public function __construct(private readonly InvoicePdfService $pdf) {}

    public function view(Invoice $invoice): Response
    {
        $this->authorizeAccess($invoice);

        return response($this->pdf->render($invoice)->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        $this->authorizeAccess($invoice, requirePrint: true);

        return response($this->pdf->render($invoice)->Output('', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$invoice->number}.pdf\"",
        ]);
    }

    private function authorizeAccess(Invoice $invoice, bool $requirePrint = false): void
    {
        $user = auth()->user();

        if ($user->isSupportUser()) {
            return;
        }

        abort_unless($invoice->customer_id === $user->customer_id, 403, __('portal.no_access_invoices'));
        abort_unless($user->canViewInvoices(), 403, __('portal.no_access_invoices'));

        if ($requirePrint) {
            abort_unless($user->canPrintInvoices(), 403, __('portal.no_access_print'));
        }
    }
}
