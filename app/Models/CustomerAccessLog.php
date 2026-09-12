<?php

namespace App\Models;

use App\Support\UserAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * یک رویدادِ دسترسیِ مشتری به پرتال — ورود، خروج، تلاشِ ناموفق یا بازدیدِ صفحه.
 *
 * فقط created_at دارد (رویداد لحظه‌ای است، ویرایش نمی‌شود). ثبت باید هرگز جریانِ
 * اصلی را نشکند؛ همهٔ فراخوانی‌ها داخلِ try/catch در record() امن شده‌اند.
 */
class CustomerAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_LOGIN        = 'login';
    public const EVENT_LOGOUT       = 'logout';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_VISIT        = 'visit';

    protected $fillable = [
        'user_id', 'username', 'customer_id', 'event', 'ip_address', 'user_agent',
        'browser', 'platform', 'device', 'url', 'route_name', 'referer',
        'languages', 'session_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * ثبتِ یک رویداد از روی درخواستِ جاری. `$username` برای تلاشِ ناموفق (که کاربری
     * احراز نشده) دستی داده می‌شود؛ در بقیهٔ حالت‌ها از خودِ کاربر خوانده می‌شود.
     */
    public static function record(string $event, Request $request, ?User $user = null, ?string $username = null): void
    {
        try {
            $ua     = (string) $request->userAgent();
            $parsed = UserAgent::parse($ua);

            self::create([
                'user_id'     => $user?->getKey(),
                'username'    => $username ?? $user?->email,
                'customer_id' => $user?->customer_id,
                'event'       => $event,
                'ip_address'  => $request->ip(),
                'user_agent'  => $ua !== '' ? Str::limit($ua, 1000, '') : null,
                'browser'     => $parsed['browser'],
                'platform'    => $parsed['platform'],
                'device'      => $parsed['device'],
                'url'         => Str::limit($request->path(), 255, ''),
                'route_name'  => $request->route()?->getName(),
                'referer'     => Str::limit((string) $request->headers->get('referer'), 255, '') ?: null,
                'languages'   => Str::limit((string) $request->headers->get('accept-language'), 120, '') ?: null,
                'session_id'  => $request->hasSession() ? $request->session()->getId() : null,
            ]);
        } catch (\Throwable) {
            // ثبتِ سابقه هرگز نباید ورود/بازدیدِ کاربر را خراب کند.
        }
    }
}
