<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('customer_id')
                        ->label(__('contracts.customer'))
                        ->options(fn () => Customer::pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Select::make('contract_plan_id')
                        ->label(__('contracts.plan'))
                        ->relationship('plan', 'name')
                        ->required()
                        ->native(false),

                    DatePicker::make('start_date')
                        ->label(__('contracts.start_date'))
                        ->required(),

                    DatePicker::make('end_date')
                        ->label(__('contracts.end_date'))
                        ->required()
                        ->afterOrEqual('start_date'),

                    TextInput::make('amount')
                        ->label(__('contracts.amount'))
                        ->numeric()
                        ->default(0)
                        ->suffix(__('common.currency')),

                    Select::make('status')
                        ->label(__('contracts.status'))
                        ->options(__('contracts.statuses'))
                        ->default('active')
                        ->required()
                        ->native(false),

                    Textarea::make('notes')
                        ->label(__('contracts.notes'))
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
