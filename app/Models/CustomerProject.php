<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * پروژه یک مشتری — مثال: «بوشهر» زیر مشتری «شرکت آریا».
 *
 * وجود این جدول دو کار می‌کند:
 *   ۱. تفکیک دسترسی — کارشناس مشتری فقط پروژه‌های خودش را می‌بیند
 *   ۲. گزارش تفکیکی — «۲۰ خدمت به آریا: ۱۰ بوشهر، ۶ چابهار، ۴ بندرعباس»
 */
class CustomerProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'code', 'name', 'city', 'location',
        'start_date', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'is_active'  => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** کارشناسان مشتری که مسئول این پروژه‌اند. مدیر مشتری اینجا ثبت نمی‌شود. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'customer_project_user')
            ->withTimestamps();
    }
}
