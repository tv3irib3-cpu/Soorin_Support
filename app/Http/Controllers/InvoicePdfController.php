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
            // پشتیبان هر فاکتوری را می‌بیند؛ ولی «چاپ/دانلود» با مجوزِ چاپ کنترل می‌شود.
            if ($requirePrint) {
                abort_unless($user->canPrintInvoices(), 403, __('portal.no_access_print'));
            }

            return;
        }

        abort_unless($user->canViewInvoices(), 403, __('portal.no_access_invoices'));
        // مدیرِ مشتری همهٔ فاکتورهای شرکت را می‌بیند؛ کارشناس فقط فاکتورهای تیکت‌های
        // خودش (دامنه در scopeVisibleToCustomer). این شرط جایگزینِ چکِ صرفِ customer_id شد.
        abort_unless(
            Invoice::visibleToCustomer($user)->whereKey($invoice->id)->exists(),
            403,
            __('portal.no_access_invoices'),
        );

        // فاکتورِ لغوشده برای مشتری باز نمی‌شود — فقط وضعیتِ «لغو شده» را می‌بیند.
        abort_if($invoice->status === Invoice::STATUS_CANCELLED, 404);

        if ($requirePrint) {
            abort_unless($user->canPrintInvoices(), 403, __('portal.no_access_print'));
        }
    }
}
