<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'amount', 'paid_at', 'method',
        'reference', 'registered_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'integer',
            'paid_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** پس از هر ثبت یا حذف پرداخت، وضعیت فاکتور به‌روز می‌شود. */
    protected static function booted(): void
    {
        static::saved(fn (Payment $p) => $p->invoice?->refreshPaymentStatus());
        static::deleted(fn (Payment $p) => $p->invoice?->refreshPaymentStatus());
    }
}
