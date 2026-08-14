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
        Ticket::observe(TicketObserver::class);
        Contract::observe(ContractObserver::class);
        TicketMessage::observe(TicketMessageObserver::class);
    }
}
