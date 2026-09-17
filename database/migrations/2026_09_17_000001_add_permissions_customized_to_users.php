<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پرچمِ «دسترسیِ سفارشی‌شده» برای هر کاربر.
 *
 * وقتی مدیر در فرمِ کاربر مجوزها را تنظیم کند این پرچم true می‌شود و از آن پس
 * دسترسیِ آن کاربر «به‌تفکیکِ خودش» (مجوزهای مستقیم) خوانده می‌شود، نه از نقش.
 * کاربرانِ موجود که هرگز ویرایش نشده‌اند false می‌مانند و دقیقاً مثلِ قبل، از
 * مجوزهای نقش استفاده می‌کنند (بدونِ هیچ تغییری در سطحِ دسترسیِ فعلی).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('permissions_customized')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('permissions_customized');
        });
    }
};
