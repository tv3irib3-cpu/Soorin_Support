<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دسته‌بندی تیکت — دولایه است و باید دولایه بماند.
 *
 *   سخت‌افزار (والد)
 *    └ هارد (فرزند)
 *
 * بدون لایه دوم، گزارش «بیشترین خرابی» بی‌فایده می‌شود.
 */
class TicketCategory extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'service_type', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    /** عنوان کامل برای نمایش: «سخت‌افزار ← هارد» */
    public function fullName(): string
    {
        return $this->parent ? "{$this->parent->name} ← {$this->name}" : $this->name;
    }
}
