<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مخاطب مشتری — دفترچه تلفن، نه حساب کاربری.
 * برای حساب ورود به پرتال از مدل User استفاده می‌شود.
 */
class CustomerContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'name', 'position', 'phone', 'mobile', 'email', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
