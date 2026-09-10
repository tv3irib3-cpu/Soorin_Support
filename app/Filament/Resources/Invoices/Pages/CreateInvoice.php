<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Ticket;
use Filament\Resources\Pages\CreateRecord;

/**
 * صدور فاکتور مستقل یا از داخل تیکت.
 * وقتی از دکمه «صدور فاکتور» روی تیکت می‌آید، پارامتر ticket در آدرس
 * است و مشتری/تیکت/قرارداد از همان تیکت پیش‌پر می‌شوند.
 */
class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function fillForm(): void
    {
        $data = [];

        if ($ticketId = request()->integer('ticket')) {
            $ticket = Ticket::find($ticketId);

            if ($ticket) {
                $data['customer_id'] = $ticket->customer_id;
                $data['ticket_id']   = $ticket->id;
                $data['contract_id'] = $ticket->contract_id
                    ?? $ticket->customer->activeContract()?->id;
            }
        }

        $this->callHook('beforeFill');
        $this->form->fill($data);
        $this->callHook('afterFill');
    }

    /**
     * برای هر «ردیفِ خدمت» که کاربر در فرم وارد کرده (یک یا چند تا)، یک
     * InvoiceItem خودکار ساخته می‌شود و در پایان جمعِ کلِ فاکتور (پوششِ قرارداد
     * + تخفیف) یک‌بار محاسبه می‌گردد — دقیقاً مثلِ افزودنِ دستیِ ردیف‌ها.
     */
    protected function afterCreate(): void
    {
        $lines = $this->data['service_items'] ?? [];

        if (! is_array($lines) || $lines === []) {
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $this->getRecord();
        $ticket  = $invoice->ticket;
        $plan    = $invoice->effectiveContractPlan();
        $created = false;

        foreach ($lines as $line) {
            $amount = (int) ($line['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $item = $invoice->items()->create([
                'item_type'  => 'service',
                'title'      => filled($line['title'] ?? null)
                    ? $line['title']
                    : __('invoices.default_service_title'),
                'quantity'   => 1,
                'unit_price' => $amount,
            ]);

            $item->recalculate(
                plan: $plan,
                serviceType: $ticket?->service_type ?? 'hardware',
                method: $ticket?->method,
            );

            $created = true;
        }

        if ($created) {
            $invoice->recalculate();   // جمعِ همهٔ ردیف‌ها در جدولِ فاکتور
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
