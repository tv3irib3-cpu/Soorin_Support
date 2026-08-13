<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * نگهبان پنل مدیریت.
 *
 * کاربران مشتری هرگز نباید وارد پنل شوند — حتی اگر آدرس را مستقیم بزنند.
 * به‌جای خطا، به پرتال خودشان هدایت می‌شوند.
 */
class EnsureSupportUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isSupportUser()) {
            // پرتال در بخش بعدی ساخته می‌شود؛ تا آن زمان دسترسی رد می‌شود
            // نه اینکه خطای «مسیر یافت نشد» بدهد.
            return Route::has('portal.dashboard')
                ? redirect()->route('portal.dashboard')
                : abort(403, __('portal.no_access'));
        }

        return $next($request);
    }
}
