<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Support\Jalali;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * آخرین تیکت‌های ثبت‌شده روی داشبورد — پاسخِ سریع به «تازه چه آمده؟».
 * کلیک روی هر سطر به همان تیکت می‌رود. فقط برای کسی که مجوزِ دیدنِ تیکت دارد.
 */
class LatestTicketsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** همان نگاشتِ رنگِ فهرستِ تیکت‌ها، تا وضعیت‌ها همه‌جا یک‌رنگ باشند. */
    private const STATUS_COLORS = [
        'new'              => 'info',
        'in_progress'      => 'warning',
        'waiting_customer' => 'gray',
        'waiting_payment'  => 'danger',
        'resolved'         => 'success',
        'closed'           => 'gray',
        'cancelled'        => 'gray',
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can(\App\Enums\Permission::ViewTickets->value) ?? false;
    }

    public function getHeading(): ?string
    {
        return __('dashboard.latest_tickets');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Ticket::query()->with('customer')->latest('id')->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Ticket $record) => TicketResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('tickets.number'))
                    ->weight('bold'),

                TextColumn::make('subject')
                    ->label(__('tickets.subject'))
                    ->limit(40),

                TextColumn::make('customer.name')
                    ->label(__('tickets.customer'))
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('status')
                    ->label(__('tickets.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("tickets.statuses.$state"))
                    ->color(fn (string $state) => self::STATUS_COLORS[$state] ?? 'gray'),

                TextColumn::make('created_at')
                    ->label(__('tickets.created_at'))
                    ->formatStateUsing(fn ($state) => Jalali::formatDateTime($state))
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),
            ]);
    }
}
