<?php

namespace App\Filament\Resources\CustomerProjects\Pages;

use App\Filament\Resources\CustomerProjects\CustomerProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerProjects extends ListRecords
{
    protected static string $resource = CustomerProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('projects.create')),
        ];
    }
}
