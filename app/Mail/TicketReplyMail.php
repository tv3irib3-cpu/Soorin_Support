<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * اطلاع‌رسانی پاسخ جدید روی تیکت.
 *
 * وقتی کارشناس پاسخ می‌دهد برای مشتری فرستاده می‌شود، و برعکس؛ هیچ‌وقت
 * برای یادداشت داخلی یا برای خودِ نویسنده پیام ارسال نمی‌شود.
 *
 * عمداً صف (ShouldQueue) نمی‌شود — هاست اشتراکی پردازش پس‌زمینه دائمی
 * ندارد و صف بدون کارگر همیشه‌درحال‌اجرا، ایمیل را برای همیشه معلق نگه
 * می‌دارد. ارسال همزمان برای این حجم کاربر کافی است.
 */
class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        // نام «message» عمداً استفاده نشد — در ویوهای Mailable لاراول این نام
        // رزرو است و به Illuminate\Mail\Message اشاره می‌کند، نه این مدل.
        public readonly TicketMessage $reply,
        public readonly string $viewUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject(__('mail.ticket_reply_subject', ['number' => $this->ticket->number]))
            ->view('mail.ticket-reply');
    }
}
