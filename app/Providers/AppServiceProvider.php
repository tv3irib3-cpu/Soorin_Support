<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Models\Contract;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\ContractObserver;
use App\Observers\TicketMessageObserver;
use App\Observers\TicketObserver;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // وقتی سرویس پیامک واقعی انتخاب شد، فقط همین خط عوض می‌شود —
        // هیچ‌جای دیگر سامانه (TicketObserver و ...) دست نمی‌خورد.
        $this->app->bind(SmsGateway::class, LogSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // هاست‌های قدیمی (MyISAM / MySQL قدیم) طولِ کلیدِ ایندکس را محدود می‌کنند؛
        // با utf8mb4، ستونِ varchar(255) از حد رد می‌شود («key too long»). محدودکردنِ
        // طولِ پیش‌فرضِ رشته به ۱۹۱ این را روی همهٔ هاست‌ها امن می‌کند.
        Schema::defaultStringLength(191);

        // روی هاست‌هایی مثلِ LiteSpeed مسیرِ زندهٔ /livewire/livewire.js با ۴۰۴ برمی‌گردد
        // (فقط فایلِ .jsِ فیزیکی سرو می‌شود، نه مسیرِ زنده). فایل‌های Livewire را publish
        // کرده‌ایم؛ اینجا آدرسِ اسکریپت را قطعاً به همان فایلِ فیزیکی می‌بندیم تا مستقل از
        // APP_PUBLIC_PATH و تشخیصِ خودکارِ Livewire کار کند و فرمِ ورود همه‌جا بالا بیاید.
        if (blank(config('livewire.asset_url'))) {
            $livewireFile = config('app.debug') ? 'livewire.js' : 'livewire.min.js';
            config(['livewire.asset_url' => '/vendor/livewire/' . $livewireFile]);
        }

        Ticket::observe(TicketObserver::class);
        Contract::observe(ContractObserver::class);
        TicketMessage::observe(TicketMessageObserver::class);
    }
}
