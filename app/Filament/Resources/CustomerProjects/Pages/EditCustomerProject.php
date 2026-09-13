<?php

namespace App\Filament\Resources\CustomerProjects\Pages;

use App\Filament\Resources\CustomerProjects\CustomerProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerProject extends EditRecord
{
    protected static string $resource = CustomerProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
