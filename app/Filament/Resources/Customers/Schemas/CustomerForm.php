<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('customers.label'))
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label(__('customers.code'))
                        ->required()
                        ->maxLength(20)
                        ->unique(ignoreRecord: true),

                    TextInput::make('name')
                        ->label(__('customers.name'))
                        ->required()
                        ->maxLength(255),

                    Select::make('entity_type')
                        ->label(__('customers.entity_type'))
                        ->options(__('customers.entity_types'))
                        ->default('company')
                        ->required()
                        ->native(false),

                    TextInput::make('national_id')
                        ->label(__('customers.national_id'))
                        ->maxLength(20),

                    TextInput::make('economic_code')
                        ->label(__('customers.economic_code'))
                        ->maxLength(30),
                ]),

            Section::make(__('common.address'))
                ->columns(2)
                ->collapsible()
                ->schema([
                    TextInput::make('phone')
                        ->label(__('common.phone'))
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('mobile')
                        ->label(__('common.mobile'))
                        ->maxLength(20),

                    TextInput::make('email')
                        ->label(__('common.email'))
                        ->email()
                        ->maxLength(255),

                    TextInput::make('city')
                        ->label(__('common.city'))
                        ->maxLength(80),

                    TextInput::make('postal_code')
                        ->label(__('common.postal_code'))
                        ->maxLength(15),

                    Textarea::make('address')
                        ->label(__('common.address'))
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make(__('customers.service_status'))
                ->description(__('customers.suspension_message_hint'))
                ->columns(2)
                ->schema([
                    Select::make('service_status')
                        ->label(__('customers.service_status'))
                        ->options(__('customers.service_statuses'))
                        ->default('active')
                        ->required()
                        ->native(false)
                        ->live(),

                    Textarea::make('suspension_message')
                        ->label(__('customers.suspension_message'))
                        ->rows(3)
                        ->columnSpanFull()
                        // فقط وقتی معنی دارد که خدمات‌دهی متوقف باشد
                        ->visible(fn ($get) => in_array($get('service_status'), ['suspended', 'blocked'], true)),
                ]),

            Section::make(__('customers.access'))
                ->description(__('customers.access_hint'))
                ->columns(2)
                ->schema([
                    Toggle::make('can_create_ticket')
                        ->label(__('customers.can_create_ticket'))
                        ->default(true),

                    Toggle::make('can_view_history')
                        ->label(__('customers.can_view_history'))
                        ->default(true),

                    Toggle::make('can_view_invoices')
                        ->label(__('customers.can_view_invoices'))
                        ->default(true)
                        ->live(),

                    Toggle::make('can_print_invoices')
                        ->label(__('customers.can_print_invoices'))
                        ->default(true)
                        // چاپ بدون امکان مشاهده بی‌معنی است
                        ->disabled(fn ($get) => ! $get('can_view_invoices'))
                        ->dehydrateStateUsing(fn ($state, $get) => $get('can_view_invoices') ? $state : false),
                ]),

            Section::make(__('customers.notes'))
                ->collapsed()
                ->schema([
                    Textarea::make('notes')
                        ->label(__('customers.notes'))
                        ->helperText(__('customers.notes_hint'))
                        ->rows(3),
                ]),
        ]);
    }
}
