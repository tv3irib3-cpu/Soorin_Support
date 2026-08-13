<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\Permission;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Invoices\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 42;

    public static function getModelLabel(): string
    {
        return __('invoices.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('invoices.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('invoices.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view'   => ViewInvoice::route('/{record}'),
            'edit'   => EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewInvoices->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageInvoices->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return $record->status === Invoice::STATUS_DRAFT
            && (auth()->user()?->can(Permission::ManageInvoices->value) ?? false);
    }

    /** فاکتور صادرشده هرگز حذف نمی‌شود — فقط لغو. پیش‌نویس قابل حذف است. */
    public static function canDelete(mixed $record): bool
    {
        return $record->status === Invoice::STATUS_DRAFT
            && (auth()->user()?->can(Permission::ManageInvoices->value) ?? false);
    }
}
