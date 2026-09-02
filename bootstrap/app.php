<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// مسیرِ پوشهٔ عمومی (public) قابلِ تغییر است — برای هاستِ اشتراکی که ریشهٔ وب باید
// «public_html» باشد نه «public». در .env مقدار APP_PUBLIC_PATH=public_html را بگذار
// تا public_path() (و در نتیجه لوگوهای برندینگ و storage:link) درست بنشیند. روی
// سرورِ اختصاصی/nginx خالی بماند تا پیش‌فرضِ «public» بماند.
if ($publicPath = env('APP_PUBLIC_PATH')) {
    $app->usePublicPath(base_path($publicPath));
}

return $app;
