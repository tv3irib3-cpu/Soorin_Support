<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// هر شب ساعت ۰۲:۳۰ — روی هاست اشتراکی با کرون‌جاب هر دقیقه (schedule:run) فعال می‌شود
Schedule::command('contracts:expire')->dailyAt('02:30');

// ضربانِ زمان‌بند + بررسیِ سررسیدِ بکاپ — هر دقیقه، اما «درون‌پردازه‌ای»
// (بدونِ ساختنِ پردازهٔ PHP جدید) تا بارِ اضافه‌ای روی سرور نیفتد. وقتی بکاپی لازم
// نیست، فقط چند خواندن/نوشتنِ کوچک روی جدولِ settings است.
//
//  ۱) ضربان: هر دقیقه یک مُهرِ زمانی می‌نویسد تا صفحهٔ پشتیبان‌گیری بفهمد که
//     زمان‌بندِ سیستم‌عامل (cron/systemd) واقعاً می‌دود یا نه — رایج‌ترین علتِ
//     «بکاپِ خودکار نگرفت» را آشکار می‌کند.
//  ۲) بکاپِ خودکار: فقط اگر زمان‌بندی روشن باشد، دستور را در همین پردازه صدا
//     می‌زند (Artisan::call، نه پردازهٔ جدید)؛ خودِ دستور تصمیم می‌گیرد که سرِ
//     ساعتِ تنظیم‌شده رسیده یا نه.
Schedule::call(function () {
    Setting::set('backup.scheduler_heartbeat', now()->toIso8601String(), 'backup', 'string');

    if (\App\Support\BackupSettings::scheduleEnabled()) {
        try {
            Artisan::call('soorin:scheduled-backup');
        } catch (\Throwable $e) {
            report($e); // شکستِ بکاپ نباید ضربان یا بقیهٔ زمان‌بند را متوقف کند
        }
    }
})->everyMinute()->name('soorin-scheduler-tick')->withoutOverlapping();
