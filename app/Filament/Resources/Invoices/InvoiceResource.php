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
use Illuminate\Database\Eloquent\Builder;

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

    /** حذفِ فاکتور فقط برای مدیرِ پشتیبان (هر وضعیتی) — طبقِ درخواستِ مالک. */
    public static function canDelete(mixed $record): bool
    {
        return auth()->user()?->isSupportAdmin() ?? false;
    }

    /**
     * دامنهٔ دیدِ فاکتورها: مدیرِ پشتیبان همه را می‌بیند؛ کارشناسِ پشتیبان فقط
     * فاکتورهای تیکت‌هایی که به خودش تخصیص یافته. (فاکتورِ بدونِ تیکت فقط برای مدیر.)
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->isSupportUser() && ! $user->isSupportAdmin()) {
            $query->whereHas('ticket', fn (Builder $q) => $q->where('assigned_to', $user->id));
        }

        return $query;
    }
}
