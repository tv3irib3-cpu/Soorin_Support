<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تنظیمات عمومی سامانه (نام شرکت، لوگو، سربرگ فاکتور، متن فوتر و ...)
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->string('group')->default('general'); // general | invoice | branding | mail
            $t->string('type')->default('string');   // string | text | bool | int | file
            $t->timestamps();
        });

        /*
        | کاربران سامانه — چهار نوع حساب:
        |
        |   support_admin   مدیر پشتیبان   — دسترسی کامل، تنها کسی که حساب می‌سازد
        |   support_staff   کارشناس پشتیبان — کار روی تیکت‌ها، بدون ساخت حساب
        |   customer_admin  مدیر مشتری     — تمام پروژه‌های همان مشتری
        |   customer_staff  کارشناس مشتری  — فقط پروژه‌های تخصیص‌داده‌شده به او
        |
        | کاربران مشتری هرگز داده مشتری دیگر را نمی‌بینند.
        */
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique()->nullable();
            $t->string('mobile', 20)->unique()->nullable();
            $t->string('password');
            $t->enum('user_type', [
                'support_admin', 'support_staff',
                'customer_admin', 'customer_staff',
            ])->default('support_staff');
            $t->unsignedBigInteger('customer_id')->nullable()->index(); // FK پس از ساخت جدول مشتریان
            $t->string('theme', 20)->default('ocean'); // ocean | night — انتخاب تم توسط هر کاربر
            $t->boolean('is_active')->default(true);

            /*
            | دسترسی در سطح خودِ حساب — لایه دوم روی دسترسی‌های مشتری.
            | null یعنی «از پیش‌فرض نقش پیروی کن».
            | این فیلدها فقط می‌توانند دسترسی را *محدودتر* کنند، نه بازتر:
            | اگر مشتری در سطح سازمان اجازه‌ای نداشته باشد، حساب هم ندارد.
            */
            $t->boolean('can_create_ticket')->nullable();
            $t->boolean('can_view_invoices')->nullable();
            $t->boolean('can_print_invoices')->nullable();

            /*
            | دامنه دیدن سوابق تیکت:
            |   none      هیچ سابقه‌ای نمی‌بیند — فقط تیکت جدید ثبت می‌کند
            |   own       فقط تیکت‌هایی که خودش ثبت کرده
            |   project   تمام تیکت‌های پروژه‌های تخصیص‌داده‌شده به او
            |   customer  تمام تیکت‌های همه پروژه‌های آن مشتری (پیش‌فرض مدیر مشتری)
            */
            $t->enum('history_scope', ['none', 'own', 'project', 'customer'])->nullable();

            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });

        // ثبت تمام تغییرات — الزام کاربر (چه کسی، کِی، چه چیزی را تغییر داد)
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 50);          // created | updated | deleted | login | stock_out ...
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('settings');
    }
};
