<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * سهمِ هر مشتری از تیکت‌های ثبت‌شده در ۳۰ روزِ اخیر — دوناتی با رنگِ اختصاصیِ
 * هر مشتری تا در نگاهِ اول قابلِ تشخیص باشد. فقط برای دارندهٔ مجوزِ گزارش.
 */
class TicketsByCustomerChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('dashboard.tickets_by_customer');
    }

    public static function canView(): bool
    {
        return auth()->user()?->can(\App\Enums\Permission::ViewReports->value) ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $since = Carbon::now()->subDays(30);

        $counts = Ticket::where('created_at', '>=', $since)
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        if ($counts->isEmpty()) {
            return ['datasets' => [['data' => []]], 'labels' => []];
        }

        $customers = Customer::whereIn('id', $counts->keys())->get()->keyBy('id');

        $labels = [];
        $data   = [];
        $colors = [];

        foreach ($counts as $customerId => $total) {
            $customer = $customers->get($customerId);
            $labels[] = $customer?->name ?? '—';
            $data[]   = (int) $total;
            $colors[] = $customer?->displayColor() ?? '#94a3b8';
        }

        return [
            'datasets' => [[
                'data'            => $data,
                'backgroundColor' => $colors,
                'borderWidth'     => 0,
            ]],
            'labels' => $labels,
        ];
    }
}
