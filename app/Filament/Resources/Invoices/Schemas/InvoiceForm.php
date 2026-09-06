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

            // ورودِ سریعِ «هزینهٔ کارِ انجام‌شده» — فقط هنگامِ صدورِ فاکتور.
            // با این مبلغ، یک ردیفِ «خدمت» خودکار ساخته می‌شود (CreateInvoice)
            // و پوششِ قرارداد/تخفیف روی آن اعمال می‌گردد. برای فاکتورهای
            // پیچیده‌تر می‌توان بعداً از بخشِ «ردیف‌های فاکتور» ردیف‌های بیشتر افزود.
            Section::make(__('invoices.quick_service'))
                ->description(__('invoices.quick_service_hint'))
                ->visibleOn('create')
                ->columns(2)
                ->schema([
                    TextInput::make('first_item_title')
                        ->label(__('invoices.item_title'))
                        ->default(__('invoices.default_service_title'))
                        ->maxLength(255)
                        ->dehydrated(false),

                    TextInput::make('first_item_amount')
                        ->label(__('invoices.service_amount_field'))
                        ->helperText(__('invoices.service_amount_hint'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix(__('common.currency'))
                        ->dehydrated(false),
                ]),
        ]);
    }
}
