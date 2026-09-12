<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\Permission;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Tickets\Schemas\TicketForm;
use App\Filament\Resources\Tickets\Tables\TicketsTable;
use App\Models\Ticket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    /**
     * دامنهٔ دیدِ تیکت‌ها در پنل: مدیرِ پشتیبان همه را می‌بیند؛ کارشناس فقط تیکت‌های
     * تخصیص‌یافته به خودش (با تغییرِ تخصیص، از پنلِ کارشناسِ قبلی پنهان می‌شود).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && $user->isSupportUser() && ! $user->isSupportAdmin()) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

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

    /** نشانِ قرمزِ تعدادِ پیام‌های خوانده‌نشده کنارِ «تیکت‌ها» در منو. */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $count = \App\Models\TicketRead::unreadCountFor($user);

        return $count > 0 ? \App\Support\Jalali::digits((string) $count) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('tickets.unread');
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
        // گفتگو دیگر به‌صورتِ جدولِ RelationManager نیست؛ در صفحهٔ نمایشِ تیکت
        // به شکلِ حباب‌های چت + دکمهٔ «پاسخ» آمده (ViewTicket) تا برای پشتیبان
        // واضح و در دسترس باشد. پیام‌ها append-only می‌مانند (بدونِ ویرایش).
        return [
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
        // مدیرِ پشتیبان (مجوزِ مدیریتِ تیکت) می‌تواند تیکتِ بسته‌شده را هم ویرایش کند —
        // برای اصلاحِ اطلاعات. قفلِ تیکت جلوی افزودنِ پیام/تغییرِ وضعیت را می‌گیرد،
        // ولی ویرایشِ رکورد توسط مدیر مجاز است. (تیکت هرگز حذف نمی‌شود.)
        return auth()->user()?->can(Permission::ManageTickets->value) ?? false;
    }

    /** تیکت هرگز حذف نمی‌شود — فقط قفل می‌شود. جدول اصلاً ستون حذف نرم ندارد. */
    public static function canDelete(mixed $record): bool
    {
        return false;
    }
}
