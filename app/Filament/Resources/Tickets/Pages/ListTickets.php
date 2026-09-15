<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    // دکمهٔ «ایجاد تیکت» برای پشتیبان (مدیر و کارشناس) در دسترس است؛ نمایش‌اش را
    // خودِ Filament با canCreate کنترل می‌کند.
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('tickets.create')),
        ];
    }
}
