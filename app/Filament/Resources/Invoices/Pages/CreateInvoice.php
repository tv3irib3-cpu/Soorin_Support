<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
