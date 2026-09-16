<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حذفِ نرمِ تیکت‌ها.
 *
 * طبقِ قاعدهٔ پروژه تیکت هرگز واقعاً حذف نمی‌شود (گزارش‌های تاریخی به آن وابسته‌اند)؛
 * پس «حذف» به‌صورتِ SoftDelete (ستونِ deleted_at) انجام می‌شود و فقط مدیرِ پشتیبان
 * می‌تواند حذف کند. رکورد در دیتابیس می‌ماند و از فهرست‌ها پنهان می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropSoftDeletes();
        });
    }
};
