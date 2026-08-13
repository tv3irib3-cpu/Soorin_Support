<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * فاکتور خدمات.
 *
 * قاعده ثابت پروژه — هر فاکتور سه عدد جدا نگه می‌دارد:
 *
 *   service_amount   ارزش واقعی خدمت      ۵٬۰۰۰٬۰۰۰
 *   contract_amount  سهمی که قرارداد داد   ۳٬۵۰۰٬۰۰۰
 *   payable_amount   آنچه مشتری می‌پردازد  ۱٬۵۰۰٬۰۰۰
 *
 * خدمت تحت پوشش کامل هم فاکتور با **مبلغ واقعی** می‌گیرد و پرداختی صفر می‌شود.
 * هرگز برای خدمت گارانتی مبلغ را صفر ثبت نکن — ارزش خدمت باید در گزارش
 * «ارزش خدمات رایگان ارائه‌شده تحت قرارداد» دیده شود.
 *
 * تمام مبالغ به **ریال** و عدد صحیح‌اند. هرگز اعشاری.
 */
class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT          = 'draft';
    public const STATUS_ISSUED         = 'issued';
    public const STATUS_PAID           = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_CANCELLED      = 'cancelled';

    protected $fillable = [
        'number', 'customer_id', 'ticket_id', 'contract_id',
        'issue_date', 'due_date',
        'service_amount', 'parts_amount', 'discount_amount',
        'contract_amount', 'payable_amount', 'paid_amount',
        'status', 'is_warranty', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'      => 'date',
            'due_date'        => 'date',
            'service_amount'  => 'integer',
            'parts_amount'    => 'integer',
            'discount_amount' => 'integer',
            'contract_amount' => 'integer',
            'payable_amount'  => 'integer',
            'paid_amount'     => 'integer',
            'is_warranty'     => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- روابط

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // --------------------------------------------------------------- محاسبه

    /**
     * محاسبه سه عدد کلیدی از روی ردیف‌ها و ثبت آن‌ها روی فاکتور.
     *
     * ترتیب کار:
     *   ۱. جمع ارزش خدمات و قطعات از ردیف‌ها
     *   ۲. جمع سهم قرارداد از ردیف‌ها (هر ردیف سهم خودش را دارد)
     *   ۳. اعمال سقف ریالی قرارداد — سهم قرارداد از مانده سقف بیشتر نمی‌شود
     *   ۴. کسر تخفیف دستی
     */
    public function recalculate(): void
    {
        $items = $this->items()->get();

        $serviceAmount = (int) $items->where('item_type', 'service')->sum('line_total');
        $partsAmount   = (int) $items->where('item_type', 'part')->sum('line_total');
        $otherAmount   = (int) $items->where('item_type', 'other')->sum('line_total');

        $grossTotal      = $serviceAmount + $partsAmount + $otherAmount;
        $contractCovered = (int) $items->sum('contract_covered');

        // سقف قرارداد — سهم قرارداد از مانده سقف فراتر نمی‌رود
        if ($this->contract && ($remaining = $this->contract->remainingCeiling()) !== null) {
            $contractCovered = min($contractCovered, $remaining);
        }

        $discount = (int) $this->discount_amount;

        // مبلغ قابل پرداخت هرگز منفی نمی‌شود
        $payable = max(0, $grossTotal - $contractCovered - $discount);

        $this->forceFill([
            'service_amount'  => $serviceAmount + $otherAmount,
            'parts_amount'    => $partsAmount,
            'contract_amount' => $contractCovered,
            'payable_amount'  => $payable,
            // «تحت گارانتی» یعنی خدمتی ارائه شده ولی مشتری چیزی نمی‌پردازد
            'is_warranty'     => $grossTotal > 0 && $payable === 0,
        ])->save();

        $this->refreshPaymentStatus();
    }

    /** به‌روزرسانی وضعیت پرداخت از روی جمع پرداخت‌های ثبت‌شده. */
    public function refreshPaymentStatus(): void
    {
        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED], true)) {
            return;
        }

        $paid = (int) $this->payments()->sum('amount');

        $status = match (true) {
            $paid >= $this->payable_amount => self::STATUS_PAID,
            $paid > 0                      => self::STATUS_PARTIALLY_PAID,
            default                        => self::STATUS_ISSUED,
        };

        $this->forceFill(['paid_amount' => $paid, 'status' => $status])->save();
    }

    /** مانده پرداخت‌نشده. */
    public function balance(): int
    {
        return max(0, $this->payable_amount - $this->paid_amount);
    }
}
