<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Customer;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label(__('users.name'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('users.email'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('mobile')->label(__('users.mobile'))->maxLength(20)->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->label(__('users.password'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? __('users.password_hint') : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state)),

                    Select::make('user_type')
                        ->label(__('users.user_type'))
                        ->options(__('auth.types'))
                        ->default(User::TYPE_SUPPORT_STAFF)
                        ->required()
                        ->native(false)
                        ->live(),

                    Select::make('customer_id')
                        ->label(__('users.customer'))
                        ->helperText(__('users.customer_hint'))
                        ->options(fn () => Customer::pluck('name', 'id'))
                        ->searchable()
                        ->required(fn ($get) => in_array($get('user_type'), [User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF]))
                        ->visible(fn ($get) => in_array($get('user_type'), [User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF])),

                    Select::make('theme')
                        ->label(__('users.theme'))
                        ->options(['ocean' => __('common.theme_ocean'), 'night' => __('common.theme_night')])
                        ->default('ocean')
                        ->native(false),

                    Toggle::make('is_active')->label(__('users.active'))->default(true),
                ]),

            Section::make(__('users.account_overrides'))
                ->description(__('users.account_overrides_hint'))
                ->columns(2)
                ->visible(fn ($get) => in_array($get('user_type'), [User::TYPE_CUSTOMER_ADMIN, User::TYPE_CUSTOMER_STAFF]))
                ->schema([
                    Select::make('can_create_ticket')
                        ->label(__('customers.can_create_ticket'))
                        ->options([1 => __('common.yes'), 0 => __('common.no')])
                        ->placeholder(__('users.follow_default'))
                        ->native(false),

                    Select::make('can_view_invoices')
                        ->label(__('customers.can_view_invoices'))
                        ->options([1 => __('common.yes'), 0 => __('common.no')])
                        ->placeholder(__('users.follow_default'))
                        ->native(false),

                    Select::make('can_print_invoices')
                        ->label(__('customers.can_print_invoices'))
                        ->options([1 => __('common.yes'), 0 => __('common.no')])
                        ->placeholder(__('users.follow_default'))
                        ->native(false),

                    Select::make('history_scope')
                        ->label(__('users.history_scope'))
                        ->options(__('users.history_scope_options'))
                        ->placeholder(__('users.follow_default'))
                        ->native(false),
                ]),
        ]);
    }
}
