<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\Permission;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('contracts.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contracts.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('contracts.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'edit'   => EditContract::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewContracts->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageContracts->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageContracts->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->can(Permission::ManageContracts->value) ?? false;
    }
}
