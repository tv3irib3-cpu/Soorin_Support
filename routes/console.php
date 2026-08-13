<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// هر شب ساعت ۰۲:۳۰ — روی هاست اشتراکی با کرون‌جاب هر دقیقه (schedule:run) فعال می‌شود
Schedule::command('contracts:expire')->dailyAt('02:30');
