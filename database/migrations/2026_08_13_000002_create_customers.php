<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();            // کد مشتری
            $t->string('name');                          // نام شخص یا شرکت
            $t->enum('entity_type', ['person', 'company'])->default('company');
            $t->string('national_id', 20)->nullable();   // شناسه ملی / کد ملی
            $t->string('economic_code', 30)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('mobile', 20)->nullable();
            $t->string('email')->nullable();
            $t->string('city', 80)->nullable();
            $t->text('address')->nullable();
            $t->string('postal_code', 15)->nullable();

            // وضعیت خدمات‌دهی — الزام کاربر
            $t->enum('service_status', ['active', 'suspended', 'blocked'])->default('active');
            $t->text('suspension_message')->nullable();  // متنی که هنگام ثبت تیکت به مشتری نمایش داده می‌شود

            // دسترسی‌های اختصاصی هر مشتری در پرتال — الزام کاربر
            $t->boolean('can_create_ticket')->default(true);
            $t->boolean('can_view_history')->default(true);
            $t->boolean('can_view_invoices')->default(true);
            $t->boolean('can_print_invoices')->default(true);

            $t->text('notes')->nullable();               // یادداشت داخلی، برای مشتری قابل مشاهده نیست
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        /*
        | پروژه‌های یک مشتری — مثال: شرکت آریا با سه پروژه بندرعباس، چابهار، بوشهر.
        |
        | مدیر مشتری هر سه را می‌بیند؛ کارشناس مشتری فقط پروژه‌های تخصیص‌داده‌شده به خودش.
        | هر تیکت به یک پروژه وصل می‌شود تا گزارش «چند خدمت به کدام پروژه» ممکن باشد.
        */
        Schema::create('customer_projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);                  // ARIA-BUS
            $t->string('name');                      // بوشهر
            $t->string('city', 80)->nullable();
            $t->string('location')->nullable();      // محل دقیق نصب
            $t->date('start_date')->nullable();      // تاریخ شروع همکاری روی این پروژه
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['customer_id', 'code']);
        });

        /*
        | تخصیص کارشناس مشتری به پروژه — چند به چند.
        | یک کارشناس می‌تواند مسئول بیش از یک پروژه باشد و یک پروژه چند کارشناس داشته باشد.
        | مدیر مشتری در این جدول ثبت نمی‌شود؛ او به‌طور خودکار به همه پروژه‌ها دسترسی دارد.
        */
        Schema::create('customer_project_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['customer_project_id', 'user_id']);
        });

        // مخاطبین یک شرکت (چند کاربر زیر یک مشتری)
        Schema::create('customer_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('position', 80)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('mobile', 20)->nullable();
            $t->string('email')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_project_user');
        Schema::dropIfExists('customer_projects');
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['customer_id']));
        Schema::dropIfExists('customers');
    }
};
