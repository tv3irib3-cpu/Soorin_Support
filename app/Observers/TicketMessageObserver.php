<?php

namespace App\Observers;

use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * سه کار خودکار روی هر پیامِ عمومیِ تازه:
 *   ۱. اولین پاسخِ عمومیِ کارشناس، first_response_at تیکت را ثبت می‌کند
 *      (مبنای محاسبهٔ نقض SLA در Ticket::isSlaBreached)
 *   ۲. جریانِ خودکارِ وضعیت: پاسخِ پشتیبان → «منتظر پاسخ مشتری»، پاسخِ مشتری →
 *      «در انتظار پاسخ پشتیبان» (فقط وقتی گفتگو هنوز باز است)
 *   ۳. اطلاع‌رسانیِ ایمیل به طرفِ مقابل
 *
 * یادداشتِ داخلی (is_internal) هرگز نه SLA را می‌بندد، نه وضعیت را عوض می‌کند و
 * نه ایمیل می‌شود. ارسالِ ایمیل در try/catch است تا خطای میل‌سرور هرگز ثبتِ پاسخ
 * را با ۵۰۰ خراب نکند (روی هاستِ زنده میل‌سرور ممکن است پاسخ ندهد).
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

            $this->autoTransition($ticket, Ticket::STATUS_WAITING_CUSTOMER);
            $this->notify($ticket->customer?->email, $ticket, $message, 'portal');
        } elseif ($author instanceof User && $author->isCustomerUser()) {
            $this->autoTransition($ticket, Ticket::STATUS_WAITING_SUPPORT);
            $this->notify($ticket->assignee?->email, $ticket, $message, 'admin');
        }
    }

    /**
     * وضعیت را به مقصد می‌برد — فقط اگر گفتگو باز باشد (حل‌شده/بسته/لغو دست‌نخورده
     * می‌ماند) و مقصد با وضعیت فعلی فرق داشته باشد. save() رویدادِ TicketObserver
     * را می‌زند تا در ticket_status_logs و تاریخچه ثبت شود.
     */
    private function autoTransition(Ticket $ticket, string $target): void
    {
        if (! $ticket->canReceiveMessages() || $ticket->status === $target) {
            return;
        }

        $ticket->forceFill(['status' => $target])->save();
    }

    private function notify(?string $email, Ticket $ticket, TicketMessage $message, string $target): void
    {
        if (blank($email)) {
            return;
        }

        try {
            $url = $target === 'portal'
                ? route('portal.tickets.show', $ticket)
                : route('filament.admin.resources.tickets.view', $ticket);

            Mail::to($email)->send(new TicketReplyMail($ticket, $message, $url));
        } catch (\Throwable $e) {
            // خطای میل‌سرور نباید ثبتِ پیام را خراب کند — فقط لاگ می‌شود.
            Log::warning('ارسالِ ایمیلِ پاسخِ تیکت ناموفق بود: ' . $e->getMessage());
        }
    }
}
