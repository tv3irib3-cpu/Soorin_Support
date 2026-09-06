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
     * اگر کاربر «هزینهٔ کارِ انجام‌شده» را در فرم وارد کرده باشد، یک ردیفِ
     * «خدمت» خودکار ساخته می‌شود و جمعِ فاکتور (پوششِ قرارداد + تخفیف) دوباره
     * محاسبه می‌گردد — دقیقاً مثلِ افزودنِ دستیِ ردیف از بخشِ «ردیف‌های فاکتور».
     */
    protected function afterCreate(): void
    {
        $amount = (int) ($this->data['first_item_amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $this->getRecord();

        $item = $invoice->items()->create([
            'item_type'  => 'service',
            'title'      => filled($this->data['first_item_title'] ?? null)
                ? $this->data['first_item_title']
                : __('invoices.default_service_title'),
            'quantity'   => 1,
            'unit_price' => $amount,
        ]);

        $ticket = $invoice->ticket;

        $item->recalculate(
            plan: $invoice->effectiveContractPlan(),
            serviceType: $ticket->service_type ?? 'hardware',
            method: $ticket->method ?? null,
        );

        $invoice->recalculate();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
