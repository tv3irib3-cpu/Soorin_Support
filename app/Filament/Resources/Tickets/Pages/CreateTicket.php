<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /** پس از ساخت، مستقیم به صفحهٔ نمایشِ همان تیکت برود. */
    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
