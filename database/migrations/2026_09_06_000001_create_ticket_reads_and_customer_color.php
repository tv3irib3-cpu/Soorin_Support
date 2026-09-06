<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دو افزودنی:
 *   ۱) جدولِ ticket_reads — آخرین زمانی که هر کاربر گفتگوی یک تیکت را دیده،
 *      برای شمارشِ پیام‌های «خوانده‌نشده» (شبیه تلگرام).
 *   ۲) ستونِ رنگ برای هر مشتری — تا در جدولِ تیکت‌ها، آمار و نمودارها با رنگِ
 *      اختصاصی‌اش قابلِ تشخیص باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('last_read_at')->nullable();
            $t->timestamps();

            $t->unique(['ticket_id', 'user_id']);
        });

        Schema::table('customers', function (Blueprint $t) {
            if (! Schema::hasColumn('customers', 'color')) {
                $t->string('color', 9)->nullable()->after('name');   // #RRGGBB
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_reads');

        Schema::table('customers', function (Blueprint $t) {
            if (Schema::hasColumn('customers', 'color')) {
                $t->dropColumn('color');
            }
        });
    }
};
