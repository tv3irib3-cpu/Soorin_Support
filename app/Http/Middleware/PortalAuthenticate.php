<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * نگهبان پرتال مشتری.
 *
 * مهمان به صفحه ورود پرتال هدایت می‌شود (نه ورود پنل). کاربر داخلی شرکت
 * هم به‌جای خطا، به پنل مدیریت خودش هدایت می‌شود.
 */
class PortalAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('portal.login');
        }

        if ($user->isSupportUser()) {
            return redirect('/admin');
        }

        if (! $user->is_active) {
            auth()->logout();

            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
