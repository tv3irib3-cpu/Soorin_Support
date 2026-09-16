<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    /**
     * پارامترِ status[] در URL (از لینک‌های داشبورد) را به فیلترِ وضعیتِ جدول
     * تبدیل می‌کند تا با کلیک روی هر باکسِ داشبورد، فهرست با همان فیلتر باز شود و
     * فیلتر در UI هم دیده و قابلِ تغییر باشد.
     */
    public function mount(): void
    {
        parent::mount();

        $statuses = array_values(array_intersect(
            (array) request()->input('status', []),
            array_keys(__('tickets.statuses')),
        ));

        if ($statuses !== []) {
            $this->tableFilters['status']['values'] = $statuses;
        }
    }

    // دکمهٔ «ایجاد تیکت» برای پشتیبان (مدیر و کارشناس) در دسترس است؛ نمایش‌اش را
    // خودِ Filament با canCreate کنترل می‌کند.
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('tickets.create')),
        ];
    }
}
