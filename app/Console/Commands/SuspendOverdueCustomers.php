<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * تعلیقِ خودکارِ مشتری در صورتِ وجودِ بدهیِ سررسیدشده.
 *
 * برای هر فاکتوری که مهلتِ پرداختش گذشته، هنوز بدهی دارد (حتی جزئی) و پیش از این
 * پردازش نشده، مشتری معلق می‌شود. با علامت‌گذاریِ overdue_suspended_at، هر فاکتور
 * فقط یک‌بار باعثِ تعلیق می‌شود؛ فاکتورِ دیگری با سررسیدِ دیگر که رد شود، دوباره چک
 * و در صورتِ فعال‌بودنِ مشتری، دوباره معلقش می‌کند. اگر مدیرِ پشتیبان مشتری را دستی
 * فعال کرده باشد و فاکتورِ تازه‌ای سررسید شود، دوباره معلق می‌گردد.
 */
class SuspendOverdueCustomers extends Command
{
    protected $signature = 'customers:suspend-overdue';

    protected $description = 'تعلیقِ خودکارِ مشتریانِ دارای بدهیِ سررسیدشده';

    public function handle(): int
    {
        $overdue = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereColumn('paid_amount', '<', 'payable_amount')
            ->whereNull('overdue_suspended_at')
            ->with('customer')
            ->get();

        $suspended = 0;

        foreach ($overdue as $invoice) {
            // این فاکتور دیگر باعثِ تعلیقِ تکراری نمی‌شود.
            $invoice->forceFill(['overdue_suspended_at' => now()])->saveQuietly();

            $customer = $invoice->customer;

            if (! $customer) {
                continue;
            }

            // اگر مشتری فعال است (چه هیچ‌وقت معلق نشده، چه مدیر دستی فعالش کرده) معلق کن.
            if ($customer->service_status === Customer::STATUS_ACTIVE) {
                $customer->forceFill([
                    'service_status'     => Customer::STATUS_SUSPENDED,
                    'suspension_message' => Customer::OVERDUE_SUSPENSION_MESSAGE,
                ])->save();

                try {
                    ActivityLog::record('customer_auto_suspended', $customer, ['invoice' => $invoice->number]);
                } catch (\Throwable) {
                    // نبودِ جدولِ سیاهه نباید کار را متوقف کند
                }

                $suspended++;
            }
        }

        $this->info("پردازش شد: {$overdue->count()} فاکتورِ سررسیدشده، {$suspended} مشتری معلق شد.");

        return self::SUCCESS;
    }
}
