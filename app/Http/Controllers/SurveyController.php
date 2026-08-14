<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * نظرسنجی رضایت — صفحه‌ای عمومی، بدون نیاز به ورود.
 *
 * دسترسی فقط از طریق لینک امضاشده (middleware: signed در routes/web.php)
 * ممکن است؛ بدون امضای معتبر یا بعد از انقضا، ۴۰۳ می‌دهد.
 *
 * بعد از ثبت، دوباره ریدایرکت به یک GET امضاشده نمی‌کنیم — چون امضای
 * فعلی فقط برای همین درخواست معتبر است؛ به‌جایش همان صفحه را با پیام
 * تشکر مستقیماً برمی‌گردانیم.
 */
class SurveyController extends Controller
{
    public function show(Ticket $ticket): View
    {
        return view('survey.show', ['ticket' => $ticket, 'submitted' => false]);
    }

    public function store(Request $request, Ticket $ticket): View
    {
        // یک نظر برای هر تیکت — دومی بازنویسی نمی‌کند
        if ($ticket->rating === null) {
            $data = $request->validate([
                'rating'         => ['required', 'integer', 'min:1', 'max:5'],
                'rating_comment' => ['nullable', 'string', 'max:2000'],
            ]);

            $ticket->forceFill($data)->save();
        }

        return view('survey.show', ['ticket' => $ticket->fresh(), 'submitted' => true]);
    }
}
