<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک ردیف فاکتور.
 *
 * هر ردیف سهم قرارداد خودش را جدا نگه می‌دارد، چون درصد پوشش برای خدمت
 * نرم‌افزاری و سخت‌افزاری و قطعه فرق می‌کند.
 *
 * ستون part_code فقط یک ارجاع متنی به سامانه انبار است — این سامانه هیچ
 * اتصال دیتابیسی به انبار ندارد.
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'item_type', 'part_code', 'title',
        'quantity', 'unit_price', 'line_total',
        'contract_cover_percent', 'contract_covered', 'payable',
    ];

    protected function casts(): array
    {
        return [
            'quantity'               => 'decimal:2',
            'unit_price'             => 'integer',
            'line_total'             => 'integer',
            'contract_cover_percent' => 'integer',
            'contract_covered'       => 'integer',
            'payable'                => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * محاسبه جمع ردیف و سهم قرارداد.
     *
     * درصد پوشش از نوع قرارداد خوانده می‌شود و اگر قراردادی نباشد صفر است.
     * گرد کردن به سمت پایین انجام می‌شود تا سهم قرارداد هرگز از مبلغ واقعی
     * بیشتر نشود.
     */
    public function recalculate(?ContractPlan $plan = null, ?string $serviceType = null, ?string $method = null): void
    {
        $lineTotal = (int) round($this->quantity * $this->unit_price);

        $percent = $plan
            ? $plan->coverPercentFor($this->item_type, $serviceType ?? 'other', $method)
            : 0;

        $percent = max(0, min(100, $percent));

        $covered = intdiv($lineTotal * $percent, 100);

        $this->forceFill([
            'line_total'             => $lineTotal,
            'contract_cover_percent' => $percent,
            'contract_covered'       => $covered,
            'payable'                => $lineTotal - $covered,
        ])->save();
    }
}
