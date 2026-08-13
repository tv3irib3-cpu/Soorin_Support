<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\Permission;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Tickets\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('tickets.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tickets.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('tickets.nav_group');
    }

    public static function form(Schema $schema): Schema
    {
        return TicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
            AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view'   => ViewTicket::route('/{record}'),
            'edit'   => EditTicket::route('/{record}/edit'),
        ];
    }

    // --------------------------------------------------------- دسترسی‌ها

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ViewTickets->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::CreateTickets->value) ?? false;
    }

    public static function canEdit(mixed $record): bool
    {
        // تیکت قفل‌شده حتی با مجوز مدیریت هم قابل ویرایش نیست
        return ! $record->is_locked && (auth()->user()?->can(Permission::ManageTickets->value) ?? false);
    }

    /** تیکت هرگز حذف نمی‌شود — فقط قفل می‌شود. جدول اصلاً ستون حذف نرم ندارد. */
    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
