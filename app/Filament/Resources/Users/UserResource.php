<?php

namespace App\Filament\Resources\Users;

use App\Enums\Permission;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * مدیریت کاربران — طبق قاعده صریح پروژه، فقط مدیر پشتیبان اجازه ساخت
 * حساب کاربری دارد (چه داخلی، چه برای مشتری).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('customers.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit'   => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewUsers->value) ?? false;
    }

    public static function canCreate(): bool
    {
        // مطابق قاعده پروژه: فقط مدیر پشتیبان — نه هر کسی با مجوز ManageUsers
        return auth()->user()?->isSupportAdmin() ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        return auth()->user()?->isSupportAdmin() ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return (auth()->user()?->isSupportAdmin() ?? false) && auth()->id() !== $record->id;
    }
}
