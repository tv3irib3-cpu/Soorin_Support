<?php

namespace App\Actions;

use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

/**
 * صدور فاکتور: تغییر وضعیت به issued + اطلاع‌رسانی ایمیل به مشتری (اگر ایمیل داشته باشد).
 * از UI (ViewInvoice) جدا شده تا بدون درگیر کردن Livewire قابل تست باشد.
 */
class IssueInvoice
{
    public function __invoke(Invoice $invoice): void
    {
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $invoice->refreshPaymentStatus();

        if (filled($invoice->customer?->email)) {
            Mail::to($invoice->customer->email)->send(new InvoiceIssuedMail(
                $invoice,
                route('invoices.pdf.view', $invoice),
            ));
        }
    }
}
