<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توکن‌های اپِ موبایل (پشتیبان/مشتری).
 *
 * برای «یک‌بار لاگین و ماندن» روی اپ — بدونِ وابستگی به Sanctum (چون به‌روزرسانیِ
 * سامانه فقط کد را کپی می‌کند و vendor را روی سرور عوض نمی‌کند). فقطِ hashِ توکن
 * ذخیره می‌شود؛ خودِ توکن یک‌بار هنگام لاگین به اپ داده می‌شود و روی گوشی امن می‌ماند.
 * این لایه از لاگینِ وب جداست، پس امنیتِ سایت را تغییر نمی‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name')->nullable();          // نامِ دستگاه/اپ
            $t->string('token', 64)->unique();        // sha256 hex از توکنِ اصلی
            $t->string('platform', 20)->nullable();   // support | portal
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
