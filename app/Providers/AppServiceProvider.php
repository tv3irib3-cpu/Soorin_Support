<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Observers\ContractObserver;
use App\Observers\TicketMessageObserver;
use App\Observers\TicketObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
