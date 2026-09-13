<?php

namespace App\Filament\Resources\CustomerProjects\Schemas;

use App\Models\User;
use App\Support\Jalali;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * فرمِ پروژه در منوی مستقلِ «پروژه‌ها».
 *
 * تفاوتِ کلیدی با مدیریتِ پروژه از داخلِ مشتری: اینجا اول باید «مشتری» انتخاب شود،
 * بعد اطلاعاتِ پروژه. فهرستِ «کارشناسانِ مسئول» هم به همان مشتریِ انتخاب‌شده محدود می‌شود.
 */
class CustomerProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('projects.customer'))
                ->description(__('projects.customer_first_hint'))
                ->schema([
                    Select::make('customer_id')
                        ->label(__('projects.customer'))
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->live()
                        // با تغییرِ مشتری، کارشناسانِ انتخاب‌شدهٔ مشتریِ قبلی پاک شوند.
                        ->afterStateUpdated(fn (Set $set) => $set('users', [])),
                ]),

            Section::make(__('projects.label'))
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label(__('projects.code'))
                        ->required()
                        ->maxLength(30),

                    TextInput::make('name')
                        ->label(__('projects.name'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('city')
                        ->label(__('projects.city'))
                        ->maxLength(80),

                    TextInput::make('location')
                        ->label(__('projects.location'))
                        ->maxLength(255),

                    DatePicker::make('start_date')
                        ->label(__('projects.start_date'))
                        ->helperText(fn ($state) => $state ? Jalali::format($state) : null)
                        ->live(onBlur: true),

                    Toggle::make('is_active')
                        ->label(__('projects.is_active'))
                        ->default(true),

                    Select::make('users')
                        ->label(__('projects.assigned_users'))
                        ->helperText(__('projects.assigned_hint'))
                        ->relationship(
                            name: 'users',
                            titleAttribute: 'name',
                            // فقط کارشناسانِ همان مشتریِ انتخاب‌شده در فرم.
                            modifyQueryUsing: fn ($query, Get $get) => $query
                                ->where('customer_id', $get('customer_id'))
                                ->where('user_type', User::TYPE_CUSTOMER_STAFF),
                        )
                        ->multiple()
                        ->preload()
                        // تا مشتری انتخاب نشده، انتخابِ کارشناس بی‌معنی است.
                        ->disabled(fn (Get $get) => blank($get('customer_id')))
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label(__('projects.notes'))
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
