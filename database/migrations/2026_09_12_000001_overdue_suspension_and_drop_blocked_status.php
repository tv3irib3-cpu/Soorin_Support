<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دو تغییر:
 *   ۱) ستونِ overdue_suspended_at روی فاکتور — نشانهٔ اینکه سیستم بابتِ سررسیدِ این
 *      فاکتور یک‌بار مشتری را معلق کرده (تا تکرار نشود؛ فاکتورِ دیگری با سررسیدِ دیگر
 *      دوباره چک می‌شود).
 *   ۲) حذفِ وضعیتِ «مسدود» (blocked) از خدمات‌دهیِ مشتری — با «معلق» یکسان عمل می‌کرد.
 *      رکوردهای blocked به suspended منتقل و enum به (active, suspended) کوتاه می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            if (! Schema::hasColumn('invoices', 'overdue_suspended_at')) {
                $t->timestamp('overdue_suspended_at')->nullable()->after('paid_amount');
            }
        });

        // رکوردهای «مسدود» → «معلق»
        DB::table('customers')->where('service_status', 'blocked')->update(['service_status' => 'suspended']);

        // enum را به دو مقدار کوتاه کن (نامِ جدول با پیشوندِ احتمالیِ دیتابیسِ مشترک)
        $table = DB::getTablePrefix() . 'customers';
        DB::statement("ALTER TABLE `$table` MODIFY `service_status` ENUM('active','suspended') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        $table = DB::getTablePrefix() . 'customers';
        DB::statement("ALTER TABLE `$table` MODIFY `service_status` ENUM('active','suspended','blocked') NOT NULL DEFAULT 'active'");

        Schema::table('invoices', function (Blueprint $t) {
            if (Schema::hasColumn('invoices', 'overdue_suspended_at')) {
                $t->dropColumn('overdue_suspended_at');
            }
        });
    }
};
