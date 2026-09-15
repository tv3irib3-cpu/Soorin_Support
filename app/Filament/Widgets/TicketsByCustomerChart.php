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

    // بدونِ wire:pollِ ۵ ثانیه‌ای (هم‌راستا با نمودارِ روند و برای پرهیز از تازه‌سازیِ بی‌مورد).
    protected ?string $pollingInterval = null;

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

    protected function getOptions(): array
    {
        return [
            'cutout'  => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels'   => ['usePointStyle' => true, 'boxWidth' => 8, 'padding' => 14],
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $since = Carbon::now()->subDays(30);

        // تیکت‌های ساختهٔ مشتری به تفکیکِ شرکت — رنگِ اختصاصیِ هر شرکت.
        $byCustomer = Ticket::createdByCustomer()
            ->where('created_at', '>=', $since)
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        // تیکت‌های ساختهٔ پشتیبان — یک بخشِ خاکستریِ واحد، جدا از رنگِ شرکت‌ها.
        $supportTotal = Ticket::createdBySupport()
            ->where('created_at', '>=', $since)
            ->count();

        if ($byCustomer->isEmpty() && $supportTotal === 0) {
            return ['datasets' => [['data' => []]], 'labels' => []];
        }

        $customers = Customer::whereIn('id', $byCustomer->keys())->get()->keyBy('id');

        $labels = [];
        $data   = [];
        $colors = [];

        foreach ($byCustomer as $customerId => $total) {
            $customer = $customers->get($customerId);
            $labels[] = $customer?->name ?? '—';
            $data[]   = (int) $total;
            $colors[] = $customer?->displayColor() ?? '#94a3b8';
        }

        if ($supportTotal > 0) {
            $labels[] = __('tickets.by_support');
            $data[]   = $supportTotal;
            $colors[] = '#94a3b8';   // خاکستری برای تیکت‌های پشتیبان
        }

        return [
            'datasets' => [[
                'data'            => $data,
                'backgroundColor' => $colors,
                'borderColor'     => 'rgba(255, 255, 255, 0.6)',
                'borderWidth'     => 2,
                'hoverOffset'     => 6,
            ]],
            'labels' => $labels,
        ];
    }
}
