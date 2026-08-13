<?php

namespace App\Filament\Resources\TicketCategories\Pages;

use App\Filament\Resources\TicketCategories\TicketCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketCategories extends ListRecords
{
    protected static string $resource = TicketCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
