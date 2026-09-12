<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سوابقِ دسترسیِ مشتریان — هر ورود/خروج/بازدید از پرتال با اطلاعاتِ فنیِ قابلِ
 * جمع‌آوری (IP، مرورگر، سیستم‌عامل، دستگاه، آدرسِ صفحه و ...) ثبت می‌شود.
 *
 * نامِ کاربری و شناسهٔ مشتری به‌صورتِ عکس‌فوری (snapshot) هم ذخیره می‌شوند تا حتی
 * پس از حذفِ حساب، سابقه معنا‌دار بماند (user_id با nullOnDelete خالی می‌شود).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_access_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username')->nullable()->index();   // ایمیل/نام‌کاربریِ لحظهٔ رویداد
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('event', 30)->index();               // login | logout | login_failed | visit
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();

            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('device', 20)->nullable();           // desktop | mobile | tablet | bot | unknown

            $table->string('url')->nullable();                  // مسیرِ درخواست‌شده
            $table->string('route_name')->nullable();
            $table->string('referer')->nullable();
            $table->string('languages')->nullable();            // از هدرِ Accept-Language
            $table->string('session_id', 64)->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_access_logs');
    }
};
