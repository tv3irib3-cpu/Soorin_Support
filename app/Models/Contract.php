<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * قرارداد پشتیبانی منعقدشده با یک مشتری.
 *
 * قرارداد تعیین می‌کند چه درصدی از هزینه هر حوزه پوشش داده شود و سقف ریالی
 * پوشش چقدر است. محاسبه سهم قرارداد در Invoice انجام می‌شود.
 */
class Contract extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = [
        'status'      => self::STATUS_ACTIVE,
        'amount'      => 0,
        'used_amount' => 0,
    ];

    protected $fillable = [
        'number', 'customer_id', 'contract_plan_id',
        'start_date', 'end_date', 'amount', 'used_amount', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date'  => 'date',
            'end_date'    => 'date',
            'amount'      => 'integer',
            'used_amount' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContractPlan::class, 'contract_plan_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** آیا قرارداد در تاریخ داده‌شده معتبر است؟ */
    public function isValidOn(?string $date = null): bool
    {
        $date ??= now()->toDateString();

        return $this->status === self::STATUS_ACTIVE
            && $this->start_date->toDateString() <= $date
            && $this->end_date->toDateString() >= $date;
    }

    /**
     * مانده سقف پوشش. اگر قرارداد سقف نداشته باشد null برمی‌گردد
     * که یعنی «بدون محدودیت».
     */
    public function remainingCeiling(): ?int
    {
        $ceiling = $this->plan?->ceiling_amount;

        if ($ceiling === null) {
            return null;
        }

        return max(0, $ceiling - $this->used_amount);
    }
}
