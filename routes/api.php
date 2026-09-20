<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
| API اپِ موبایل (پشتیبان/مشتری). احرازِ هویت با توکنِ Bearerِ ماندگار — جدا از
| نشستِ وب، پس امنیتِ سایت را تغییر نمی‌دهد. همهٔ آدرس‌ها با پیشوندِ /api.
*/

// ---- عمومی (بدونِ توکن) ----
Route::get('app-version', [\App\Http\Controllers\Api\AppController::class, 'version']);
Route::post('support/login', [AuthController::class, 'loginSupport']);
Route::post('portal/login', [AuthController::class, 'loginPortal']);

// ---- مشترک بینِ هر دو اپ (هر توکنِ معتبر) ----
// me/logout و دانلودِ پیوست هر دو اپ لازم دارند؛ کنترلرِ پیوست خودش دسترسی را
// بر اساسِ Ticket::visibleTo بررسی می‌کند، پس بازبودنش برای مشتری امن است.
Route::middleware(AuthenticateApiToken::class)->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('attachments/{attachment}', [TicketAttachmentController::class, 'download'])
        ->name('api.attachments.show');
});

// ---- اپِ پشتیبان (فقط توکنِ support) ----
Route::middleware(AuthenticateApiToken::class . ':support')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('tickets', [TicketController::class, 'index']);
    Route::get('tickets/meta', [TicketController::class, 'meta']);          // پیش از {ticket}
    Route::get('tickets/form-data', [TicketController::class, 'formData']); // پیش از {ticket}
    Route::get('tickets/staff', [TicketController::class, 'staff']);        // پیش از {ticket}
    Route::post('tickets', [TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve']);
    Route::post('tickets/{ticket}/status', [TicketController::class, 'changeStatus']);
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign']);

    Route::get('invoices', [\App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [\App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/pay', [\App\Http\Controllers\Api\InvoiceController::class, 'pay']);
    Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\InvoicePdfController::class, 'view']);

    Route::get('customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
    Route::get('customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);
});

// ---- اپِ مشتری (فقط توکنِ portal) ----
Route::middleware(AuthenticateApiToken::class . ':portal')->prefix('portal')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Api\Portal\DashboardController::class, 'index']);

    Route::get('tickets', [\App\Http\Controllers\Api\Portal\TicketController::class, 'index']);
    Route::get('tickets/form-data', [\App\Http\Controllers\Api\Portal\TicketController::class, 'formData']);
    Route::get('tickets/staff', [\App\Http\Controllers\Api\Portal\TicketController::class, 'staff']);
    Route::post('tickets', [\App\Http\Controllers\Api\Portal\TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [\App\Http\Controllers\Api\Portal\TicketController::class, 'show']);
    Route::post('tickets/{ticket}/reply', [\App\Http\Controllers\Api\Portal\TicketController::class, 'reply']);
    Route::post('tickets/{ticket}/rate', [\App\Http\Controllers\Api\Portal\TicketController::class, 'rate']);
    Route::post('tickets/{ticket}/assign', [\App\Http\Controllers\Api\Portal\TicketController::class, 'assign']);

    Route::get('invoices', [\App\Http\Controllers\Api\Portal\InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\InvoicePdfController::class, 'view']);
});
