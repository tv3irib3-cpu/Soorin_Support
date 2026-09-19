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

// ---- ورود (بدونِ توکن) ----
Route::post('support/login', [AuthController::class, 'loginSupport']);
Route::post('portal/login', [AuthController::class, 'loginPortal']);

// ---- مسیرهای نیازمندِ توکن ----
Route::middleware(AuthenticateApiToken::class)->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('tickets', [TicketController::class, 'index']);
    Route::get('tickets/meta', [TicketController::class, 'meta']);   // پیش از {ticket}
    Route::post('tickets', [TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::post('tickets/{ticket}/resolve', [TicketController::class, 'resolve']);
    Route::post('tickets/{ticket}/status', [TicketController::class, 'changeStatus']);

    Route::get('attachments/{attachment}', [TicketAttachmentController::class, 'download'])
        ->name('api.attachments.show');
});
