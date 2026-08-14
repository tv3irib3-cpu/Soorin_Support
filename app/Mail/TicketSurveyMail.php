<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * دعوت به نظرسنجی رضایت — وقتی تیکت «حل‌شده» می‌شود فرستاده می‌شود.
 * لینک داخلش امضاشده است (URL::temporarySignedRoute)، پس مشتری بدون
 * ورود هم می‌تواند نظر بدهد — اصطکاک کمتر یعنی نرخ پاسخ بالاتر.
 * مثل بقیه ایمیل‌های سامانه، همزمان ارسال می‌شود، نه صف.
 */
class TicketSurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $surveyUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject(__('mail.survey_subject', ['number' => $this->ticket->number]))
            ->view('mail.ticket-survey');
    }
}
