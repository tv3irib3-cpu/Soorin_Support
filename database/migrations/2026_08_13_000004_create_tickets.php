<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // دسته‌بندی دولایه: والد (سخت‌افزار) ← فرزند (هارد)
        Schema::create('ticket_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('ticket_categories')->cascadeOnDelete();
            $t->string('name', 100);
            $t->enum('service_type', ['software', 'hardware'])->default('hardware');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();                  // شماره تیکت قابل نمایش
            $t->foreignId('customer_id')->constrained();
            // پروژه‌ای که تیکت برای آن ثبت شده — مبنای گزارش «چند خدمت به کدام پروژه»
            $t->foreignId('customer_project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ticket_category_id')->nullable()->constrained();
            // نام سامانه مرتبط — به‌صورت متن آزاد وارد می‌شود.
            // سامانه‌های نصب‌شده در پروژه جداگانه «انبار و پروژه» نگهداری می‌شوند و
            // این دو سامانه هیچ اتصال دیتابیسی به هم ندارند.
            $t->string('system_name')->nullable();
            $t->foreignId('contract_id')->nullable()->constrained();

            $t->string('subject');
            $t->text('description');
            $t->enum('service_type', ['software', 'hardware'])->default('hardware');
            $t->enum('method', ['phone', 'remote', 'onsite'])->nullable();   // تلفنی | ریموت | حضوری
            $t->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $t->enum('status', [
                'new', 'in_progress', 'waiting_customer',
                'waiting_payment', 'resolved', 'closed', 'cancelled',
            ])->default('new');

            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // کارشناس مسئول
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $t->unsignedInteger('work_minutes')->default(0);      // زمان صرف‌شده
            $t->text('resolution')->nullable();                   // شرح راه‌حل نهایی

            $t->timestamp('first_response_at')->nullable();       // برای محاسبه SLA
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->boolean('is_locked')->default(false);             // تیکت بسته‌شده قفل می‌شود، حذف نمی‌شود

            $t->unsignedTinyInteger('rating')->nullable();        // نظرسنجی رضایت
            $t->text('rating_comment')->nullable();

            $t->timestamps();
            $t->index(['customer_id', 'status']);
            $t->index(['status', 'created_at']);
            $t->index(['customer_project_id', 'created_at']); // گزارش تفکیکی هر پروژه
        });

        // گفتگوی رفت‌وبرگشتی + یادداشت داخلی
        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('body');
            $t->boolean('is_internal')->default(false);          // فقط کارشناس می‌بیند، مشتری نه
            $t->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ticket_message_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });

        // تغییرات وضعیت تیکت
        Schema::create('ticket_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_logs');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
