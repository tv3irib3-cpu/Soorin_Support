<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * اطلاع‌رسانی صدور فاکتور به مشتری. مثل TicketReplyMail عمداً صف نمی‌شود.
 */
class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $viewUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject(__('mail.invoice_issued_subject', ['number' => $this->invoice->number]))
            ->view('mail.invoice-issued');
    }
}
