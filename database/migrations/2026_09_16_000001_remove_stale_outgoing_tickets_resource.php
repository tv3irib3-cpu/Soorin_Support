<?php

use Illuminate\Database\Migrations\Migration;

/**
 * پاک‌سازیِ منبعِ حذف‌شدهٔ «تیکت‌های خروجی».
 *
 * چون به‌روزرسانیِ سامانه به‌صورتِ copy-over است (فایل‌ها رونویسی می‌شوند ولی
 * فایلِ حذف‌شده باقی می‌ماند)، کلاس‌های OutgoingTickets روی نصب‌های قدیمی مانده‌اند
 * و چون کلیدِ ترجمهٔ «tickets.outgoing» هم برداشته شده، در منو به‌صورتِ متنِ خام
 * دیده می‌شوند. این مهاجرت آن پوشهٔ کد را حذف می‌کند. هیچ داده‌ای دست نمی‌خورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rrmdir(app_path('Filament/Resources/OutgoingTickets'));
    }

    public function down(): void
    {
        // بازگردانی معنا ندارد؛ این کد دیگر وجود ندارد.
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
            $path = $dir . '/' . $name;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
};
