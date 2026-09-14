<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    // تیکتِ «ورودی» را مشتری از پرتال می‌سازد؛ دکمهٔ ساخت اینجا معنی ندارد.
    // (ساختِ تیکت توسطِ پشتیبان در منوی «تیکت‌های خروجی» است.)
    protected function getHeaderActions(): array
    {
        return [];
    }
}
