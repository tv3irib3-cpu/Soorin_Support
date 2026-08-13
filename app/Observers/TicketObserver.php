<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketStatusLog;

/**
 * رفتار خودکار تیکت:
 *   - شماره‌گذاری خودکار هنگام ایجاد
 *   - ثبت هر تغییر وضعیت در ticket_status_logs (هرگز حذف نمی‌شود)
 *   - قفل شدن خودکار و ثبت زمان هنگام رسیدن به «بسته‌شده»
 *   - ثبت زمان اولین پاسخ و زمان حل
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
        }
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
