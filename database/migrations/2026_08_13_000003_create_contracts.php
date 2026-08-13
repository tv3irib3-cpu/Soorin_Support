<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // انواع قرارداد پشتیبانی: طلایی، نقره‌ای، برنزی و هر نوع دلخواه دیگر
        Schema::create('contract_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);                       // طلایی
            $t->string('color', 20)->default('#14b8a6');  // رنگ نشان در پنل
            $t->text('description')->nullable();

            // دامنه پوشش: هر کدام درصدی از هزینه را پوشش می‌دهد (۰ تا ۱۰۰)
            $t->unsignedTinyInteger('cover_software')->default(0);
            $t->unsignedTinyInteger('cover_hardware')->default(0);
            $t->unsignedTinyInteger('cover_parts')->default(0);
            $t->unsignedTinyInteger('cover_onsite')->default(0);   // اعزام کارشناس

            $t->unsignedBigInteger('ceiling_amount')->nullable();  // سقف ریالی پوشش در طول قرارداد
            $t->unsignedInteger('included_tickets')->nullable();   // تعداد تیکت رایگان
            $t->unsignedInteger('response_hours')->nullable();     // SLA — زمان پاسخ تعهدشده
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // قرارداد منعقدشده با یک مشتری
        Schema::create('contracts', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contract_plan_id')->constrained();
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedBigInteger('amount')->default(0);          // مبلغ قرارداد (ریال)
            $t->unsignedBigInteger('used_amount')->default(0);     // مقدار مصرف‌شده از سقف
            $t->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_plans');
    }
};
