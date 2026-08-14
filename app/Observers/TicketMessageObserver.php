<?php

namespace App\Observers;

use App\Models\TicketMessage;
use App\Models\User;

/**
 * اولین پاسخ کارشناس (نه مشتری، نه یادداشت داخلی) زمان first_response_at
 * تیکت را ثبت می‌کند — مبنای محاسبه نقض SLA در Ticket::isSlaBreached.
 */
class TicketMessageObserver
{
    public function created(TicketMessage $message): void
    {
        if ($message->is_internal) {
            return;
        }

        $user = $message->user;

        if (! $user instanceof User || ! $user->isSupportUser()) {
            return;
        }

        $ticket = $message->ticket;

        if ($ticket && $ticket->first_response_at === null) {
            $ticket->forceFill(['first_response_at' => $message->created_at ?? now()])->save();
        }
    }
}
