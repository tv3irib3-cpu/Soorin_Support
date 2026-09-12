<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لوگوی مشتری — روی دیسکِ branding (public/branding/customers) ذخیره می‌شود؛
 * اینجا فقط مسیرِ نسبیِ فایل نگه داشته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
