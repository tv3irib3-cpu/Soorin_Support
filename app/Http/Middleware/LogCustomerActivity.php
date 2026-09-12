<?php

namespace App\Http\Middleware;

use App\Models\CustomerAccessLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ثبتِ بازدیدِ صفحاتِ پرتال توسطِ کاربرانِ مشتری.
 *
 * فقط ناوبریِ واقعی ثبت می‌شود: درخواستِ GET که AJAX نیست (پُلِ شمارندهٔ
 * پیام‌های خوانده‌نشده و مانندِ آن با X-Requested-With کنار می‌روند تا سابقه
 * پر از نویز نشود). ورود/خروج جداگانه در AuthController ثبت می‌شوند.
 */
class LogCustomerActivity
{
    /** مسیرهایی که ثبتِ بازدیدشان ارزشی ندارد (پولینگ/داده‌ای، نه صفحه). */
    private const SKIP_ROUTES = ['portal.unread'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->wantsJson()
            && $user instanceof User
            && $user->isCustomerUser()
            && ! in_array($request->route()?->getName(), self::SKIP_ROUTES, true)) {
            CustomerAccessLog::record(CustomerAccessLog::EVENT_VISIT, $request, $user);
        }

        return $response;
    }
}
