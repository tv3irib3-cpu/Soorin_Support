<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\EditRecord;

/**
 * تیکت هرگز حذف نمی‌شود — فقط قفل. بنابراین اینجا DeleteAction وجود ندارد.
 */
class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
