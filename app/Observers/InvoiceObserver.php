<?php

namespace App\Observers;

use App\Models\Invoice;
use Hekmatinasser\Verta\Verta;

/**
 * شماره‌گذاریِ خودکارِ فاکتور و ثبتِ صادرکننده.
 *
 * ستونِ invoices.number یکتا و NOT NULL است ولی فرمِ فاکتور فیلدِ شماره ندارد؛
 * بدونِ این Observer، ساختِ فاکتور با خطای دیتابیس شکست می‌خورد
 * («خطا در بارگذاری صفحه»). قالب: F-1405-0001 (سالِ شمسی + شمارندهٔ سالانه).
 */
class InvoiceObserver
{
    public function creating(Invoice $invoice): void
    {
        if (blank($invoice->number)) {
            $invoice->number = $this->nextNumber();
        }

        if (blank($invoice->created_by) && auth()->check()) {
            $invoice->created_by = auth()->id();
        }

        $this->coerceAmounts($invoice);
    }

    public function updating(Invoice $invoice): void
    {
        $this->coerceAmounts($invoice);
    }

    /**
     * ستون‌های مبلغیِ فاکتور NOT NULL هستند؛ اگر فرم فیلدِ خالی بفرستد (مثلِ
     * تخفیفِ خالی)، null باعثِ خطای دیتابیس و «خطا در بارگذاری صفحه» می‌شود.
     * اینجا هر مبلغِ null به صفر تبدیل می‌شود — تورِ ایمنیِ نهایی.
     */
    private function coerceAmounts(Invoice $invoice): void
    {
        foreach (['service_amount', 'parts_amount', 'discount_amount', 'contract_amount', 'payable_amount', 'paid_amount'] as $col) {
            if ($invoice->{$col} === null) {
                $invoice->{$col} = 0;
            }
        }
    }

    private function nextNumber(): string
    {
        $year = Verta::now()->format('Y');

        $last = Invoice::where('number', 'like', "F-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('F-%s-%04d', $year, $sequence);
    }
}
