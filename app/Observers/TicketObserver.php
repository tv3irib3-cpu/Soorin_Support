<?php

namespace App\Observers;

use App\Mail\TicketSurveyMail;
use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketStatusLog;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * رفتار خودکار تیکت:
 *   - شماره‌گذاری خودکار هنگام ایجاد
 *   - ثبت هر تغییر وضعیت در ticket_status_logs (هرگز حذف نمی‌شود)
 *   - قفل شدن خودکار و ثبت زمان هنگام رسیدن به «بسته‌شده»
 *   - ثبت زمان اولین پاسخ و زمان حل
 *   - دعوت به نظرسنجی رضایت هنگام رسیدن به «حل‌شده»
 *   - پیامک: تیکت جدید ← تیم پشتیبانی، حل‌شدن ← مشتری (اگر sms.enabled باشد)
 */
class TicketObserver
{
    public function creating(Ticket $ticket): void
    {
        if (blank($ticket->number)) {
            $ticket->number = $this->nextNumber();
        }
    }

    public function created(Ticket $ticket): void
    {
        TicketStatusLog::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => auth()->id(),
            'from_status' => null,
            'to_status'   => $ticket->status,
        ]);

        ActivityLog::record('created', $ticket);

        $this->notifySupportTeamBySms($ticket);
    }

    public function updating(Ticket $ticket): void
    {
        if (! $ticket->isDirty('status')) {
            return;
        }

        $from = $ticket->getOriginal('status');
        $to   = $ticket->status;

        if ($to === Ticket::STATUS_RESOLVED && blank($ticket->resolved_at)) {
            $ticket->resolved_at = now();
        }

        if ($to === Ticket::STATUS_CLOSED) {
            $ticket->is_locked = true;
            $ticket->closed_at ??= now();
        }

        // اگر از بسته‌شده به وضعیت دیگری برگردد (فقط توسط مدیر پشتیبان مجاز است)
        if ($from === Ticket::STATUS_CLOSED && $to !== Ticket::STATUS_CLOSED) {
            $ticket->is_locked = false;
        }
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('status')) {
            TicketStatusLog::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => auth()->id(),
                'from_status' => $ticket->getOriginal('status'),
                'to_status'   => $ticket->status,
            ]);

            ActivityLog::record('status_changed', $ticket, [
                'from' => $ticket->getOriginal('status'),
                'to'   => $ticket->status,
            ]);

            if ($ticket->status === Ticket::STATUS_RESOLVED) {
                $this->sendSurveyInvite($ticket);
                $this->notifyCustomerBySms($ticket);
            }
        }
    }

    /** پیامک ثبت تیکت جدید به مدیر پشتیبان و کارشناسان — نه به مشتری. */
    private function notifySupportTeamBySms(Ticket $ticket): void
    {
        if (! SmsService::isEnabled()) {
            return;
        }

        $mobiles = User::whereIn('user_type', [User::TYPE_SUPPORT_ADMIN, User::TYPE_SUPPORT_STAFF])
            ->where('is_active', true)
            ->pluck('mobile')
            ->all();

        app(SmsService::class)->sendToMany(
            $mobiles,
            __('sms.new_ticket_text', ['number' => $ticket->number, 'customer' => $ticket->customer?->name]),
        );
    }

    /**
     * پیامک حل‌شدن تیکت به مدیر مشتری و کارشناس مشتریِ همان پروژه (اگر تیکت
     * به پروژه‌ای وصل باشد). کارشناسان مشتری در پروژه‌های دیگر پیامک نمی‌گیرند.
     */
    private function notifyCustomerBySms(Ticket $ticket): void
    {
        if (! SmsService::isEnabled() || ! $ticket->customer_id) {
            return;
        }

        $recipients = User::where('customer_id', $ticket->customer_id)
            ->where('is_active', true)
            ->where(function ($q) use ($ticket) {
                $q->where('user_type', User::TYPE_CUSTOMER_ADMIN);

                if ($ticket->customer_project_id) {
                    $q->orWhereHas('projects', fn ($p) => $p->where('customer_projects.id', $ticket->customer_project_id));
                }
            })
            ->pluck('mobile')
            ->all();

        app(SmsService::class)->sendToMany(
            $recipients,
            __('sms.resolved_text', ['number' => $ticket->number]),
        );
    }

    /** دعوت به نظرسنجی — فقط اگر مشتری ایمیل داشته باشد و هنوز نظر نداده باشد. */
    private function sendSurveyInvite(Ticket $ticket): void
    {
        $email = $ticket->customer?->email;

        if (blank($email) || $ticket->rating !== null) {
            return;
        }

        $url = URL::temporarySignedRoute('survey.show', now()->addDays(30), ['ticket' => $ticket]);

        Mail::to($email)->send(new TicketSurveyMail($ticket, $url));
    }

    /** شماره تیکت به قالب T-14050522-0001 — سال‌ماه‌روز شمسی + شمارنده روزانه. */
    private function nextNumber(): string
    {
        // Jalali::format ارقام را فارسی برمی‌گرداند؛ کد داخلی باید انگلیسی بماند
        $prefix = 'T-' . \Hekmatinasser\Verta\Verta::now()->format('Ymd');

        $lastToday = Ticket::where('number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('number');

        $sequence = $lastToday ? ((int) substr($lastToday, -4)) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
