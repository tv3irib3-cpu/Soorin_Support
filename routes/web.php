<?php

use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\InvoiceController as PortalInvoiceController;
use App\Http\Controllers\Portal\TicketController as PortalTicketController;
use App\Http\Controllers\SurveyController;
use App\Http\Middleware\ApplyUserTheme;
use App\Http\Middleware\PortalAuthenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// مسیر پیش‌فرض ورود — میان‌افزار auth لاراول برای کاربر مهمان به این نام
// ریدایرکت می‌کند. تا ساخت صفحه ورود پرتال (فاز بعد)، به ورود پنل می‌رود.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// theme.css مستقیماً از resources سرو می‌شود (نه کپی در public) تا با نسخه‌ای
// که پنل Filament هم می‌خواند همیشه یکسان بماند — یک فایل، یک منبع حقیقت.
Route::get('/css/theme.css', function () {
    return response(file_get_contents(resource_path('css/theme.css')), 200, [
        'Content-Type'  => 'text/css',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('theme.css');

// خارج از مسیریابی Filament تا هم پنل مدیریت و هم پرتال مشتری (فاز ۷) از آن
// استفاده کنند؛ مجوز داخل خودِ کنترلر بررسی می‌شود.
Route::middleware('auth')->group(function () {
    Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'view'])->name('invoices.pdf.view');
    Route::get('/invoices/{invoice}/pdf/download', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
});

// ---------------------------------------------------------------- پرتال مشتری
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', [PortalAuthController::class, 'show'])->name('login');
    Route::post('/login', [PortalAuthController::class, 'login']);
    Route::post('/logout', [PortalAuthController::class, 'logout'])->middleware('auth')->name('logout');

    Route::middleware([PortalAuthenticate::class, ApplyUserTheme::class])->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');

        Route::get('/tickets', [PortalTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/create', [PortalTicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [PortalTicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [PortalTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [PortalTicketController::class, 'reply'])->name('tickets.reply');

        Route::get('/invoices', [PortalInvoiceController::class, 'index'])->name('invoices.index');
    });
});

// ------------------------------------------------------------ نظرسنجی رضایت
// عمومی و بدون ورود — فقط با لینک امضاشده که هنگام «حل‌شدن» تیکت ایمیل می‌شود.
// فرم همان آدرس امضاشده را برای POST هم دوباره استفاده می‌کند (ApplyUserTheme
// لازم نیست، صفحه تم ثابت ocean دارد مثل صفحه ورود).
Route::middleware('signed')->group(function () {
    Route::get('/survey/{ticket}', [SurveyController::class, 'show'])->name('survey.show');
    Route::post('/survey/{ticket}', [SurveyController::class, 'store'])->name('survey.store');
});
