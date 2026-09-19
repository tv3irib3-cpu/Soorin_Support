<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * توکنِ دسترسیِ اپِ موبایل. فقط hashِ توکن نگه داشته می‌شود.
 */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token', 'platform', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ساختِ توکنِ تازه برای یک کاربر و برگرداندنِ مقدارِ اصلیِ آن (فقط همین یک‌بار).
     */
    public static function issue(User $user, string $name, string $platform): string
    {
        $plain = Str::random(64);

        static::create([
            'user_id'   => $user->id,
            'name'      => mb_substr(trim($name) ?: 'app', 0, 255),
            'token'     => hash('sha256', $plain),
            'platform'  => $platform,
        ]);

        return $plain;
    }

    /** یافتنِ توکن از روی مقدارِ اصلی (Bearer). */
    public static function findByPlain(?string $plain): ?self
    {
        if (blank($plain)) {
            return null;
        }

        return static::with('user')->where('token', hash('sha256', $plain))->first();
    }
}
