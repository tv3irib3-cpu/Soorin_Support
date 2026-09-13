<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * پشتیبانیِ «نشانِ فاکتورِ جدید» در پرتالِ مشتری:
 *   - invoices.issued_at: لحظه‌ای که فاکتور برای مشتری قابل‌دیدن شد (وضعیت issued).
 *   - جدولِ invoice_reads: آخرین باری که هر کاربر فهرستِ فاکتورها را دید.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('issued_at')->nullable()->after('status');
        });

        // فاکتورهای موجودِ غیرِ پیش‌نویس، از قبل «صادرشده» بوده‌اند؛ زمانِ ساختشان
        // را به‌عنوانِ issued_at می‌گذاریم تا ستون خالی نماند.
        DB::table('invoices')
            ->whereNotIn('status', ['draft'])
            ->whereNull('issued_at')
            ->update(['issued_at' => DB::raw('created_at')]);

        Schema::create('invoice_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->unique('user_id');
        });

        // کاربرانِ فعلیِ مشتری را «به‌روز» علامت می‌زنیم تا پس از نصب، فاکتورهای
        // قدیمی به‌اشتباه «جدید» شمرده نشوند — فقط صدورهای بعد از این لحظه جدیدند.
        $now = now();
        $users = DB::table('users')
            ->whereIn('user_type', ['customer_admin', 'customer_staff'])
            ->pluck('id');

        foreach ($users as $id) {
            DB::table('invoice_reads')->insert([
                'user_id'      => $id,
                'last_seen_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reads');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('issued_at');
        });
    }
};
