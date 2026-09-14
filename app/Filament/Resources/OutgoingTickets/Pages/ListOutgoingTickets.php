<?php

namespace App\Filament\Resources\OutgoingTickets\Pages;

use App\Filament\Resources\OutgoingTickets\OutgoingTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutgoingTickets extends ListRecords
{
    protected static string $resource = OutgoingTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('tickets.create_outgoing')),
        ];
    }
}
