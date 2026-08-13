<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerProject;
use App\Models\TicketCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('tickets.label'))
                ->columns(2)
                ->schema([
                    Select::make('customer_id')
                        ->label(__('tickets.customer'))
                        ->options(fn () => Customer::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        // با عوض شدن مشتری، پروژه و قرارداد انتخاب‌شده قبلی معتبر نیست
                        ->afterStateUpdated(fn ($set) => $set('customer_project_id', null)),

                    Select::make('customer_project_id')
                        ->label(__('tickets.project'))
                        ->options(fn ($get) => $get('customer_id')
                            ? CustomerProject::where('customer_id', $get('customer_id'))->pluck('name', 'id')
                            : [])
                        ->native(false)
                        ->searchable(),

                    Select::make('ticket_category_id')
                        ->label(__('tickets.category'))
                        // فقط زیردسته‌ها قابل انتخاب‌اند؛ دسته والد صرفاً برای گروه‌بندی است
                        ->options(fn () => TicketCategory::whereNotNull('parent_id')
                            ->with('parent')
                            ->get()
                            ->mapWithKeys(fn (TicketCategory $c) => [$c->id => $c->fullName()]))
                        ->searchable()
                        ->native(false),

                    Select::make('contract_id')
                        ->label(__('tickets.contract'))
                        ->options(fn ($get) => $get('customer_id')
                            ? Contract::where('customer_id', $get('customer_id'))
                                ->where('status', Contract::STATUS_ACTIVE)
                                ->pluck('number', 'id')
                            : [])
                        ->helperText(__('contracts.no_active'))
                        ->native(false),

                    TextInput::make('system_name')
                        ->label(__('tickets.system_name'))
                        ->helperText(__('tickets.system'))
                        ->maxLength(255),

                    TextInput::make('subject')
                        ->label(__('tickets.subject'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label(__('tickets.description'))
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make(__('common.actions'))
                ->columns(3)
                ->schema([
                    Select::make('service_type')
                        ->label(__('tickets.service_type'))
                        ->options(__('tickets.service_types'))
                        ->default('hardware')
                        ->required()
                        ->native(false),

                    Select::make('method')
                        ->label(__('tickets.method'))
                        ->options(__('tickets.methods'))
                        ->native(false),

                    Select::make('priority')
                        ->label(__('tickets.priority'))
                        ->options(__('tickets.priorities'))
                        ->default('normal')
                        ->required()
                        ->native(false),
                ]),
        ]);
    }
}
