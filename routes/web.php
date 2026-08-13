<?php

use App\Http\Controllers\InvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// مسیر پیش‌فرض ورود — میان‌افزار auth لاراول برای کاربر مهمان به این نام
// ریدایرکت می‌کند. تا ساخت صفحه ورود پرتال (فاز بعد)، به ورود پنل می‌رود.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// خارج از مسیریابی Filament تا هم پنل مدیریت و هم پرتال مشتری (فاز ۷) از آن
// استفاده کنند؛ مجوز داخل خودِ کنترلر بررسی می‌شود.
Route::middleware('auth')->group(function () {
    Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'view'])->name('invoices.pdf.view');
    Route::get('/invoices/{invoice}/pdf/download', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
});
