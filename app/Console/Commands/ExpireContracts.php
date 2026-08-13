<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;

/**
 * قراردادهایی که تاریخ پایانشان گذشته را «منقضی» علامت می‌زند.
 * هر شب اجرا می‌شود (routes/console.php).
 */
class ExpireContracts extends Command
{
    protected $signature = 'contracts:expire';

    protected $description = 'علامت‌گذاری قراردادهای منقضی‌شده';

    public function handle(): int
    {
        $count = Contract::where('status', Contract::STATUS_ACTIVE)
            ->whereDate('end_date', '<', now())
            ->update(['status' => Contract::STATUS_EXPIRED]);

        $this->info("{$count} قرارداد منقضی علامت‌گذاری شد.");

        return self::SUCCESS;
    }
}
