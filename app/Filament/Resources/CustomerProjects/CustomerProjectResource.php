<?php

namespace App\Filament\Resources\CustomerProjects;

use App\Enums\Permission;
use App\Filament\Resources\CustomerProjects\Pages\CreateCustomerProject;
use App\Filament\Resources\CustomerProjects\Pages\EditCustomerProject;
use App\Filament\Resources\CustomerProjects\Pages\ListCustomerProjects;
use App\Filament\Resources\CustomerProjects\Schemas\CustomerProjectForm;
use App\Filament\Resources\CustomerProjects\Tables\CustomerProjectsTable;
use App\Models\CustomerProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * منوی مستقلِ «پروژه‌ها» — همهٔ پروژه‌های همهٔ مشتریان یک‌جا، با رنگِ مشتری کنارِ
 * هر پروژه، سورت و فیلتر. مدیریتِ پروژه از داخلِ خودِ مشتری (RelationManager) هم
 * دست‌نخورده می‌ماند؛ این فقط یک نمای سراسری و راحت‌ترِ ساخت است.
 */
class CustomerProjectResource extends Resource
{
    protected static ?string $model = CustomerProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 11;   // درست بعد از «مشتریان» (۱۰)

    public static function getModelLabel(): string
    {
        return __('projects.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('projects.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('projects.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('customers.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerProjectsTable::configure($table);
    }

    /** نامِ مشتری برای رنگِ نقطه و ستون، بدونِ N+1 خوانده می‌شود. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('customer');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCustomerProjects::route('/'),
            'create' => CreateCustomerProject::route('/create'),
            'edit'   => EditCustomerProject::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // --------------------------------------------------------- دسترسی‌ها

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewCustomers->value) ?? false;
    }

    private static function canManage(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can(Permission::ManageProjects->value) || $user?->can(Permission::ManageCustomers->value));
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(mixed $record): bool
    {
        return static::canManage();
    }

    public static function canDelete(mixed $record): bool
    {
        return static::canManage();
    }

    public static function canDeleteAny(): bool
    {
        return static::canManage();
    }
}
