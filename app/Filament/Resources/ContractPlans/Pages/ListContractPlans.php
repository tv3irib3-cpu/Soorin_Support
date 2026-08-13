<?php

namespace App\Filament\Resources\ContractPlans\Pages;

use App\Filament\Resources\ContractPlans\ContractPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContractPlans extends ListRecords
{
    protected static string $resource = ContractPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
