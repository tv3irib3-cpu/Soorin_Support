<?php

namespace App\Observers;

use App\Mail\TicketReplyMail;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * دو کار خودکار روی هر پیام تازه:
 *   ۱. اولین پاسخ عمومی کارشناس، first_response_at تیکت را ثبت می‌کند
 *      (مبنای محاسبه نقض SLA در Ticket::isSlaBreached)
 *   ۲. اطلاع‌رسانی ایمیل به طرف مقابل — کارشناس پاسخ داد یعنی به مشتری،
 *      مشتری پاسخ داد یعنی به کارشناس مسئول (اگر تخصیص داده شده باشد)
 *
 * یادداشت داخلی (is_internal) هرگز نه SLA را می‌بندد و نه ایمیل می‌شود.
 */
class TicketMessageObserver
{
    public function created(TicketMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }

        $author = $message->user;
        $ticket = $message->ticket;

        if (! $ticket) {
            return;
        }

        if ($author instanceof User && $author->isSupportUser()) {
            if ($ticket->first_response_at === null) {
                $ticket->forceFill(['first_response_at' => $message->created_at ?? now()])->save();
            }

            $this->notify($ticket->customer?->email, $ticket, $message, 'portal');
        } elseif ($author instanceof User && $author->isCustomerUser()) {
            $this->notify($ticket->assignee?->email, $ticket, $message, 'admin');
        }
    }

    private function notify(?string $email, \App\Models\Ticket $ticket, TicketMessage $message, string $target): void
    {
        if (blank($email)) {
            return;
        }

        $url = $target === 'portal'
            ? route('portal.tickets.show', $ticket)
            : route('filament.admin.resources.tickets.view', $ticket);

        Mail::to($email)->send(new TicketReplyMail($ticket, $message, $url));
    }
}
