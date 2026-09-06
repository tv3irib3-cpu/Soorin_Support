<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * دانلودِ پیوستِ تیکت — با کنترلِ دسترسی.
 *
 * کاربرِ پشتیبان همه را می‌بیند؛ کاربرِ مشتری فقط پیوستِ تیکت‌هایی را که در
 * دامنهٔ دسترسی‌اش هست (Ticket::visibleTo) و فقط اگر پیامِ متناظر داخلی نباشد.
 * فایل با نامِ اصلیِ خودش به کاربر تحویل داده می‌شود، نه کدِ ذخیره‌شده.
 */
class TicketAttachmentController extends Controller
{
    public function download(Request $request, TicketAttachment $attachment): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $ticket = $attachment->ticket;
        abort_unless($ticket instanceof Ticket, 404);

        if ($user->isSupportUser()) {
            // پشتیبان همه را می‌بیند
        } else {
            abort_unless(Ticket::visibleTo($user)->whereKey($ticket->id)->exists(), 404);
            // پیوستِ یادداشتِ داخلی هرگز به مشتری داده نمی‌شود
            abort_if($attachment->message?->is_internal, 404);
        }

        abort_unless(Storage::disk(TicketAttachmentService::DISK)->exists($attachment->path), 404);

        // با ?view=1 فایل به‌صورتِ inline (نمایش در مرورگر) برمی‌گردد — برای پیش‌نمایشِ
        // عکس و PDF داخلِ چت؛ وگرنه دانلود با نامِ اصلی.
        $disposition = $request->boolean('view') ? 'inline' : 'attachment';

        return Storage::disk(TicketAttachmentService::DISK)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime ?: 'application/octet-stream'],
            $disposition,
        );
    }
}
