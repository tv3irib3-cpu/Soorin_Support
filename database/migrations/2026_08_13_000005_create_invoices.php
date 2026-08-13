<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();

            $t->date('issue_date');
            $t->date('due_date')->nullable();

            // سه عدد کلیدی — الزام کاربر:
            $t->unsignedBigInteger('service_amount')->default(0);  // ارزش واقعی خدمت
            $t->unsignedBigInteger('parts_amount')->default(0);    // بهای قطعات
            $t->unsignedBigInteger('discount_amount')->default(0); // تخفیف دستی
            $t->unsignedBigInteger('contract_amount')->default(0); // سهم قرارداد / گارانتی
            $t->unsignedBigInteger('payable_amount')->default(0);  // مبلغ قابل پرداخت مشتری (می‌تواند صفر باشد)
            $t->unsignedBigInteger('paid_amount')->default(0);

            $t->enum('status', ['draft', 'issued', 'paid', 'partially_paid', 'cancelled'])->default('draft');
            $t->boolean('is_warranty')->default(false);            // کاملاً تحت پوشش قرارداد
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['customer_id', 'status']);
            $t->index('issue_date');
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->enum('item_type', ['service', 'part', 'other'])->default('service');
            // ردیف قطعه به‌صورت دستی تایپ می‌شود؛ این سامانه به انبار متصل نیست.
            $t->string('part_code', 40)->nullable();   // کد کالا در سامانه انبار — فقط جهت ارجاع
            $t->string('title');
            $t->decimal('quantity', 12, 2)->default(1);
            $t->unsignedBigInteger('unit_price')->default(0);
            $t->unsignedBigInteger('line_total')->default(0);
            $t->unsignedTinyInteger('contract_cover_percent')->default(0); // درصد پوششی که قرارداد داد
            $t->unsignedBigInteger('contract_covered')->default(0);
            $t->unsignedBigInteger('payable')->default(0);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('amount');
            $t->date('paid_at');
            $t->enum('method', ['cash', 'card', 'transfer', 'cheque', 'other'])->default('transfer');
            $t->string('reference', 100)->nullable();
            $t->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        // خدمات قابل ارائه با نرخ پایه — برای صدور سریع فاکتور
        Schema::create('service_rates', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->enum('service_type', ['software', 'hardware', 'other'])->default('hardware');
            $t->enum('method', ['phone', 'remote', 'onsite', 'any'])->default('any');
            $t->unsignedBigInteger('base_price')->default(0);
            $t->string('unit', 30)->default('مورد');   // مورد | ساعت | بازدید
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_rates');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
