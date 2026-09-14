<?php

namespace App\Filament\Resources\OutgoingTickets;

use App\Enums\Permission;
use App\Filament\Resources\OutgoingTickets\Pages\CreateOutgoingTicket;
use App\Filament\Resources\OutgoingTickets\Pages\ListOutgoingTickets;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * «تیکت‌های خروجی» — تیکت‌هایی که خودِ پشتیبان ساخته (نه مشتری). نمای سراسری،
 * از همان مدل و جدول و فرمِ تیکت استفاده می‌کند؛ باز/ویرایشِ رکورد در صفحاتِ
 * TicketResource انجام می‌شود (recordUrl جدول به همان‌جا می‌رود).
 */
class OutgoingTicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 31;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->createdBySupport();
        $user = auth()->user();

        // کارشناس فقط تیکت‌های تخصیص‌یافته به خودش را می‌بیند.
        if ($user && $user->isSupportUser() && ! $user->isSupportAdmin()) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    public static function getModelLabel(): string
    {
        return __('tickets.outgoing_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tickets.outgoing');
    }

    public static function getNavigationLabel(): string
    {
        return __('tickets.outgoing');
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

    public static function getPages(): array
    {
        return [
            'index'  => ListOutgoingTickets::route('/'),
            'create' => CreateOutgoingTicket::route('/create'),
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
        return auth()->user()?->can(Permission::ManageTickets->value) ?? false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
