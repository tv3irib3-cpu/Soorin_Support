<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اختصاصِ تیکت به کارشناسِ خودِ مشتری.
 *
 * مدیرِ مشتری می‌تواند تیکتی را که پشتیبانِ شرکت برایش فرستاده به یکی از
 * کارشناسانِ خودش بسپارد. این ستون مستقل از assigned_to (کارشناسِ پشتیبانِ
 * شرکت) است و فقط طرفِ مشتری را نشان می‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->foreignId('customer_assigned_to')
                ->nullable()
                ->after('assigned_to')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_assigned_to');
        });
    }
};
