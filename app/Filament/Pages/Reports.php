<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\ReportService;
use App\Support\Jalali;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Hekmatinasser\Verta\Verta;

/**
 * گزارش‌های فاز ۲ — درآمد، حجم خدمات، خدمات هر مشتری، آمار خرابی،
 * عملکرد کارشناسان. بازه پیش‌فرض «این ماه» (شمسی) است.
 */
class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 92;

    public ?array $data = [];

    public array $report = [];

    public static function getNavigationLabel(): string
    {
        return __('reports.label');
    }

    public function getTitle(): string
    {
        return __('reports.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('activity.nav_group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can(Permission::ViewReports->value), 403);

        $this->form->fill(['preset' => 'this_month']);
        $this->applyPreset();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        Select::make('preset')
                            ->label(__('reports.period_label'))
                            ->options([
                                'this_week'     => __('reports.presets.this_week'),
                                'this_month'    => __('reports.presets.this_month'),
                                'last_month'    => __('reports.presets.last_month'),
                                'last_3_months' => __('reports.presets.last_3_months'),
                                'this_year'     => __('reports.presets.this_year'),
                                'last_year'     => __('reports.presets.last_year'),
                                'year'          => __('reports.presets.year'),
                                'all_time'      => __('reports.presets.all_time'),
                                'custom'        => __('reports.presets.custom'),
                            ])
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyPreset()),

                        Select::make('year')
                            ->label(__('reports.select_year'))
                            ->options($this->yearOptions())
                            ->default((int) Verta::now()->format('Y'))
                            ->visible(fn ($get) => $get('preset') === 'year')
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->applyPreset()),

                        DatePicker::make('from')
                            ->label(__('reports.from_date'))
                            ->visible(fn ($get) => $get('preset') === 'custom')
                            ->live(),

                        DatePicker::make('to')
                            ->label(__('reports.to_date'))
                            ->visible(fn ($get) => $get('preset') === 'custom')
                            ->live(),
                    ]),
            ]);
    }

    /** تبدیل گزینه پیش‌فرض به بازه واقعی تاریخ شمسی و محاسبه گزارش. */
    public function applyPreset(): void
    {
        $preset = $this->data['preset'] ?? 'this_month';
        $now    = Verta::now();
        $y      = (int) $now->format('Y');
        $m      = (int) $now->format('m');

        [$from, $to] = match ($preset) {
            'this_week'     => [Carbon::now()->startOfWeek(Carbon::SATURDAY), Carbon::today()->endOfDay()],
            'last_month'    => $this->monthBoundsAgo($y, $m, 1),
            'last_3_months' => [$this->monthBoundsAgo($y, $m, 2)[0], Carbon::today()->endOfDay()],
            'this_year'     => [Carbon::instance(Verta::createJalali($y, 1, 1, 0, 0, 0)->datetime()), Carbon::today()->endOfDay()],
            'last_year'     => $this->jalaliYearBounds($y - 1),
            'year'          => $this->jalaliYearBounds((int) ($this->data['year'] ?? $y)),
            'all_time'      => [Carbon::create(2000, 1, 1)->startOfDay(), Carbon::today()->endOfDay()],
            'custom'        => [
                filled($this->data['from'] ?? null) ? Carbon::parse($this->data['from']) : Carbon::today()->startOfMonth(),
                filled($this->data['to'] ?? null) ? Carbon::parse($this->data['to'])->endOfDay() : Carbon::today()->endOfDay(),
            ],
            default         => $this->monthBoundsAgo($y, $m, 0),
        };

        // برای بازه‌های آماده، تاریخ‌های نمایشی هر بار با بازه محاسبه‌شده هم‌راستا
        // می‌شوند؛ فقط در حالت «دلخواه» چیزی که کاربر خودش تایپ کرده دست‌نخورده می‌ماند.
        if ($preset !== 'custom') {
            $this->data['from'] = $from->toDateString();
            $this->data['to']   = $to->toDateString();
        }

        $this->report = app(ReportService::class)->generate($from, $to);
    }

    /**
     * بازهٔ یک سالِ کاملِ شمسی (از ۱ فروردین تا پایانِ اسفند).
     * @return array{0: Carbon, 1: Carbon}
     */
    private function jalaliYearBounds(int $year): array
    {
        $start = Carbon::instance(Verta::createJalali($year, 1, 1, 0, 0, 0)->datetime());
        $end   = Carbon::instance(Verta::createJalali($year + 1, 1, 1, 0, 0, 0)->datetime())->subSecond();

        return [$start, $end];
    }

    /**
     * فهرستِ سال‌های شمسی برای انتخابگرِ «سالِ خاص» — از امسال تا ۷ سالِ قبل.
     * @return array<int, string>
     */
    private function yearOptions(): array
    {
        $current = (int) Verta::now()->format('Y');
        $options = [];

        for ($y = $current; $y >= $current - 7; $y--) {
            $options[$y] = \App\Support\Jalali::digits((string) $y);
        }

        return $options;
    }

    /**
     * بازه یک ماه شمسی، N ماه قبل از (سال,ماه) داده‌شده.
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthBoundsAgo(int $year, int $month, int $monthsAgo): array
    {
        $totalMonths = ($year * 12 + ($month - 1)) - $monthsAgo;
        $y = intdiv($totalMonths, 12);
        $m = ($totalMonths % 12) + 1;

        $nextTotal = $totalMonths + 1;
        $ny = intdiv($nextTotal, 12);
        $nm = ($nextTotal % 12) + 1;

        $start = Carbon::instance(Verta::createJalali($y, $m, 1, 0, 0, 0)->datetime());
        $end   = Carbon::instance(Verta::createJalali($ny, $nm, 1, 0, 0, 0)->datetime())->subSecond();

        return [$start, $end];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label(__('reports.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('reports.export.excel', [
                    'from' => $this->data['from'], 'to' => $this->data['to'],
                ]))
                ->openUrlInNewTab(),

            Action::make('exportPdf')
                ->label(__('reports.export_pdf'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn () => route('reports.export.pdf', [
                    'from' => $this->data['from'], 'to' => $this->data['to'],
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
