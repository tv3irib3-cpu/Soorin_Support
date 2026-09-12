<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * کاربرانِ پشتیبانی که پیش از افزوده‌شدنِ ثبتِ «آخرین ورود» واردِ پنل شده‌اند،
 * نشستِ زنده‌شان از آن زمان باقی مانده و رویدادِ Login دیگر برایشان اجرا نمی‌شود؛
 * پس ستونِ آخرین ورودشان خالی می‌ماند. این نگهبان، در نخستین درخواستِ پس از
 * به‌روزرسانی، اگر «آخرین ورود» خالی باشد آن را یک‌بار پر می‌کند تا فهرستِ کاربران
 * برای همه معنا‌دار شود. ورودهای بعدیِ واقعی همچنان توسطِ رویدادِ Login به‌روز می‌شوند.
 */
class BackfillLastLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_active && $user->last_login_at === null) {
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->saveQuietly();
        }

        return $next($request);
    }
}
