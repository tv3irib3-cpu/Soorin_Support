<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Support\Jalali;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Carbon;

/**
 * روندِ ماهانهٔ تیکت‌ها — تعدادِ ثبت‌شده و حل‌شده در هر ماه.
 *
 * بازهٔ زمانی با دو انتخاب‌گرِ «ماهِ شمسی» (از / تا) قابل‌تنظیم است (آیکونِ قیف کنارِ
 * عنوان). پیش‌فرض: ۶ ماهِ اخیر. برچسبِ ماه‌ها شمسی است.
 */
class TicketsTrendChart extends ChartWidget
{
    use HasFiltersSchema;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

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

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => ['precision' => 0, 'stepSize' => 1],
                ],
            ],
        ];
    }

    /** فرمِ فیلترِ بازه: دو انتخاب‌گرِ ماهِ شمسی (از / تا). */
    public function filtersSchema(Schema $schema): Schema
    {
        $months = $this->monthOptions();

        return $schema->components([
            Select::make('from')
                ->label(__('dashboard.trend_from'))
                ->options($months)
                ->default(Carbon::now()->startOfMonth()->subMonths(5)->format('Y-m'))
                ->native(false)
                ->searchable(),

            Select::make('to')
                ->label(__('dashboard.trend_to'))
                ->options($months)
                ->default(Carbon::now()->startOfMonth()->format('Y-m'))
                ->native(false)
                ->searchable(),
        ]);
    }

    /** فهرستِ ۳۶ ماهِ اخیر: کلید = «Y-m» میلادی، برچسب = ماه/سالِ شمسی. */
    private function monthOptions(): array
    {
        $options = [];
        $base = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 36; $i++) {
            $m = $base->copy()->subMonths($i);
            $options[$m->format('Y-m')] = Jalali::format($m, 'F Y');
        }

        return $options;
    }

    protected function getData(): array
    {
        $from = $this->monthStart($this->filters['from'] ?? null, Carbon::now()->startOfMonth()->subMonths(5));
        $to   = $this->monthStart($this->filters['to'] ?? null, Carbon::now()->startOfMonth());

        // اگر «از» بعد از «تا» بود، جابه‌جا کن تا همیشه بازهٔ درستی باشد.
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $labels = [];
        $created = [];
        $resolved = [];

        $cursor = $from->copy();
        $guard = 0;   // سقفِ ۴۸ ماه تا نمودار بی‌نهایت بزرگ نشود

        while ($cursor->lte($to) && $guard < 48) {
            $start = $cursor->copy()->startOfMonth();
            $end   = $cursor->copy()->endOfMonth();

            $labels[] = Jalali::format($cursor, 'F Y');
            $created[] = Ticket::whereBetween('created_at', [$start, $end])->count();
            $resolved[] = Ticket::whereNotNull('resolved_at')->whereBetween('resolved_at', [$start, $end])->count();

            $cursor->addMonth();
            $guard++;
        }

        return [
            'datasets' => [
                ['label' => __('dashboard.created_tickets'), 'data' => $created],
                ['label' => __('dashboard.resolved_tickets'), 'data' => $resolved],
            ],
            'labels' => $labels,
        ];
    }

    private function monthStart(?string $value, Carbon $fallback): Carbon
    {
        if (blank($value)) {
            return $fallback->copy();
        }

        try {
            return Carbon::parse($value . '-01')->startOfMonth();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }
}
