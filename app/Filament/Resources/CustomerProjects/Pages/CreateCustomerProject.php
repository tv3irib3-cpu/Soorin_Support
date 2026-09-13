<?php

namespace App\Filament\Resources\CustomerProjects\Pages;

use App\Filament\Resources\CustomerProjects\CustomerProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerProject extends CreateRecord
{
    protected static string $resource = CustomerProjectResource::class;
}
