<?php

namespace App\Filament\Resources\OutgoingTickets\Pages;

use App\Filament\Resources\OutgoingTickets\OutgoingTicketResource;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOutgoingTicket extends CreateRecord
{
    protected static string $resource = OutgoingTicketResource::class;

    /** سازندهٔ تیکت = کاربرِ پشتیبانِ واردشده، تا «خروجی» شناخته شود. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    /** پس از ساخت، به صفحهٔ نمایشِ تیکت (در منبعِ تیکت‌ها) برود. */
    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
