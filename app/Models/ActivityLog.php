<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ثبت تغییرات مهم — چه کسی، کِی، چه چیزی را تغییر داد.
 * این رکوردها هرگز حذف نمی‌شوند.
 */
class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** ثبت یک رویداد. */
    public static function record(string $action, ?Model $subject = null, array $changes = []): self
    {
        return self::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'changes'      => $changes ?: null,
            'ip_address'   => request()->ip(),
        ]);
    }
}
