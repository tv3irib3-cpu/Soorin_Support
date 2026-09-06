<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * افزودنِ وضعیتِ «در انتظار پاسخ پشتیبان» (waiting_support) به ستونِ enum تیکت.
 *
 * ستونِ status از نوعِ ENUM است و مقدارِ جدید باید به فهرستِ مجازش افزوده شود،
 * وگرنه ذخیره با خطای «Data truncated» شکست می‌خورد. با ALTER خام انجام می‌شود
 * چون Schema Builder تغییرِ enum را مستقیم پشتیبانی نمی‌کند. نامِ جدول با پیشوندِ
 * احتمالیِ دیتابیسِ مشترک (DB_TABLE_PREFIX) ساخته می‌شود.
 */
return new class extends Migration
{
    private function table(): string
    {
        return DB::getTablePrefix() . 'tickets';
    }

    public function up(): void
    {
        $t = $this->table();

        DB::statement("ALTER TABLE `$t` MODIFY `status` ENUM("
            . "'new','in_progress','waiting_customer','waiting_support','waiting_payment','resolved','closed','cancelled'"
            . ") NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        $t = $this->table();

        // مقادیرِ waiting_support را پیش از حذفِ آن از enum، به in_progress برگردان.
        DB::table('tickets')->where('status', 'waiting_support')->update(['status' => 'in_progress']);

        DB::statement("ALTER TABLE `$t` MODIFY `status` ENUM("
            . "'new','in_progress','waiting_customer','waiting_payment','resolved','closed','cancelled'"
            . ") NOT NULL DEFAULT 'new'");
    }
};
