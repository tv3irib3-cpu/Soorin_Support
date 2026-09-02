<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Support\Jalali;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * روندِ تیکت‌ها در ۶ ماهِ اخیر — تعدادِ ثبت‌شده و حل‌شده در هر ماه.
 *
 * ماه‌ها میلادی سطل‌بندی می‌شوند (مرزِ دقیق مهم نیست) ولی برچسب‌ها شمسی‌اند.
 * فقط برای کسی که مجوزِ گزارش دارد دیده می‌شود.
 */
class TicketsTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    private const MONTHS = 6;

    public function getHeading(): ?string
    {
        return __('dashboard.tickets_trend');
    }

    public static function canView(): bool
    {
        return auth()->user()?->can(\App\Enums\Permission::ViewReports->value) ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $labels = [];
        $created = [];
        $resolved = [];

        $start = Carbon::now()->startOfMonth()->subMonths(self::MONTHS - 1);

        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $start->copy()->addMonths($i);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();

            $labels[] = Jalali::format($month, 'F'); // نامِ ماهِ شمسی

            $created[] = Ticket::whereBetween('created_at', [$from, $to])->count();
            $resolved[] = Ticket::whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$from, $to])
                ->count();
        }

        return [
            'datasets' => [
                ['label' => __('dashboard.created_tickets'), 'data' => $created],
                ['label' => __('dashboard.resolved_tickets'), 'data' => $resolved],
            ],
            'labels' => $labels,
        ];
    }
}
