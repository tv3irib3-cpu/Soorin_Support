<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * «آمار مشتریان» — کارتِ جداگانه در داشبورد: تعداد کل، فعال (سبز)، معلق (قرمز)،
 * و تعدادِ مدیران/کارشناسانِ مشتری.
 */
class CustomerStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return __('dashboard.customer_stats');
    }

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewCustomers->value) ?? false;
    }

    protected function getStats(): array
    {
        $fa = fn (int $n) => \App\Support\Jalali::digits((string) $n);

        $total     = Customer::count();
        $active     = Customer::where('service_status', Customer::STATUS_ACTIVE)->count();
        $suspended  = Customer::where('service_status', '!=', Customer::STATUS_ACTIVE)->count();
        $admins     = User::where('user_type', User::TYPE_CUSTOMER_ADMIN)->count();
        $staff      = User::where('user_type', User::TYPE_CUSTOMER_STAFF)->count();

        return [
            Stat::make(__('dashboard.total_customers'), $fa($total))
                ->icon('heroicon-o-building-office-2')
                ->color('info'),

            Stat::make(__('dashboard.active_customers'), $fa($active))
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(__('dashboard.suspended_customers'), $fa($suspended))
                ->icon('heroicon-o-no-symbol')
                ->color($suspended > 0 ? 'danger' : 'gray'),

            Stat::make(__('dashboard.customer_admins'), $fa($admins))
                ->icon('heroicon-o-user-circle')
                ->color('warning'),

            Stat::make(__('dashboard.customer_staff'), $fa($staff))
                ->icon('heroicon-o-users')
                ->color('gray'),
        ];
    }
}
