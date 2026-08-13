<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Ticket;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('customer_id')
                        ->label(__('invoices.customer'))
                        ->options(fn () => Customer::pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('contract_id', null)),

                    Select::make('ticket_id')
                        ->label(__('invoices.ticket'))
                        ->options(fn ($get) => $get('customer_id')
                            ? Ticket::where('customer_id', $get('customer_id'))->pluck('number', 'id')
                            : [])
                        ->searchable()
                        ->native(false),

                    Select::make('contract_id')
                        ->label(__('invoices.contract'))
                        ->options(fn ($get) => $get('customer_id')
                            ? Contract::where('customer_id', $get('customer_id'))
                                ->where('status', Contract::STATUS_ACTIVE)
                                ->pluck('number', 'id')
                            : [])
                        ->helperText(__('contracts.no_active'))
                        ->native(false),

                    DatePicker::make('issue_date')
                        ->label(__('invoices.issue_date'))
                        ->default(now())
                        ->required(),

                    DatePicker::make('due_date')
                        ->label(__('invoices.due_date')),

                    TextInput::make('discount_amount')
                        ->label(__('invoices.discount_amount'))
                        ->numeric()
                        ->default(0)
                        ->suffix(__('common.currency')),

                    Textarea::make('notes')
                        ->label(__('invoices.notes'))
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
