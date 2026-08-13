<?php

namespace App\Filament\Resources\TicketCategories;

use App\Enums\Permission;
use App\Filament\Resources\TicketCategories\Pages\ListTicketCategories;
use App\Models\TicketCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * دسته‌بندی دولایه تیکت — سخت‌افزار ← هارد.
 *
 * بدون لایه دوم گزارش «بیشترین خرابی» بی‌فایده می‌شود، پس این ریسورس
 * عمداً هر دو لایه را در یک جدول ساده نشان می‌دهد (نه درخت تودرتو).
 */
class TicketCategoryResource extends Resource
{
    protected static ?string $model = TicketCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('tickets.categories');
    }

    public static function getNavigationLabel(): string
    {
        return __('tickets.categories');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('tickets.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('parent_id')
                ->label(__('tickets.category_parent'))
                ->helperText(__('tickets.category_hint'))
                ->options(fn () => TicketCategory::whereNull('parent_id')->pluck('name', 'id'))
                ->native(false),

            TextInput::make('name')
                ->label(__('common.name'))
                ->required()
                ->maxLength(100),

            Select::make('service_type')
                ->label(__('tickets.service_type'))
                ->options(__('tickets.service_types'))
                ->required()
                ->native(false),

            TextInput::make('sort_order')
                ->label(__('tickets.sort_order'))
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label(__('common.active'))
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parent.name')
                    ->label(__('tickets.category_parent'))
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('service_type')
                    ->label(__('tickets.service_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("tickets.service_types.$state")),

                IconColumn::make('is_active')
                    ->label(__('common.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('common.empty_state'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketCategories::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ManageTickets->value) ?? false;
    }
}
