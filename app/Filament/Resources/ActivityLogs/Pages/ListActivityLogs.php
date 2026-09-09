<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /** تاریخچهٔ تغییرات فقط‌خواندنی است — دکمهٔ «ایجاد رویداد» بی‌معنی است و حذف شد. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
