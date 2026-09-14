<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * دو تغییرِ چرخهٔ تیکت:
 *   ۱. حذفِ وضعیتِ «بسته‌شده» — چون با «حل‌شده» یکی بود. تیکت‌های بسته‌شدهٔ موجود
 *      به «حل‌شده» منتقل می‌شوند (resolved_at از closed_at پر می‌شود، قفل می‌ماند).
 *   ۲. «روش انجام» از تک‌مقداری (enum) به چندمقداری (JSON) تغییر می‌کند تا بشود
 *      ترکیبی از ریموت/حضوری/تلفنی/چت را انتخاب کرد. مقادیرِ تک‌مقداریِ فعلی به
 *      آرایهٔ JSON تبدیل می‌شوند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $p = DB::getTablePrefix();

        // ۱) بسته‌شده → حل‌شده
        DB::statement("UPDATE {$p}tickets SET status = 'resolved', resolved_at = COALESCE(resolved_at, closed_at) WHERE status = 'closed'");

        // ۲) method: enum → varchar (تا JSON و مقدارِ تازهٔ «chat» را بپذیرد)
        DB::statement("ALTER TABLE {$p}tickets MODIFY method VARCHAR(120) NULL");

        // مقادیرِ تک‌مقداریِ فعلی را به آرایهٔ JSON تبدیل کن (مثلاً remote → ["remote"]).
        DB::statement("UPDATE {$p}tickets SET method = CONCAT('[\"', method, '\"]') WHERE method IS NOT NULL AND method <> '' AND method NOT LIKE '[%'");
    }

    public function down(): void
    {
        $p = DB::getTablePrefix();

        // بازگرداندنِ JSON به تک‌مقدار (اولین مقدار) — تقریبی، فقط برای سازگاری.
        DB::statement("UPDATE {$p}tickets SET method = JSON_UNQUOTE(JSON_EXTRACT(method, '$[0]')) WHERE method LIKE '[%'");
        DB::statement("ALTER TABLE {$p}tickets MODIFY method ENUM('phone','remote','onsite') NULL");
    }
};
