<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * آخرین زمانی که هر کاربر گفتگوی یک تیکت را دیده — مبنای شمارشِ «خوانده‌نشده».
 *
 * پیامِ خوانده‌نشده برای یک کاربر = پیامی که خودش نفرستاده و بعد از آخرین
 * بازدیدش ثبت شده. مشتری یادداشتِ داخلی را اصلاً نمی‌شمارد.
 */
class TicketRead extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    /** ثبتِ اینکه این کاربر همین حالا گفتگوی این تیکت را دید. */
    public static function markRead(Ticket $ticket, User $user): void
    {
        static::updateOrCreate(
            ['ticket_id' => $ticket->id, 'user_id' => $user->id],
            ['last_read_at' => now()],
        );
    }

    /** تعداد پیام‌های خوانده‌نشدهٔ یک تیکت برای این کاربر. */
    public static function unreadForTicket(Ticket $ticket, User $user): int
    {
        return static::unreadMessagesQuery($user)
            ->where('ticket_messages.ticket_id', $ticket->id)
            ->count();
    }

    /**
     * مجموعِ پیام‌های خوانده‌نشده در همهٔ تیکت‌هایی که کاربر می‌بیند.
     * پشتیبان همه را می‌بیند؛ مشتری فقط دامنهٔ دسترسیِ خودش.
     */
    public static function unreadCountFor(User $user): int
    {
        $q = static::unreadMessagesQuery($user);

        if (! $user->isSupportUser()) {
            $visibleIds = Ticket::visibleTo($user)->pluck('tickets.id');
            $q->whereIn('ticket_messages.ticket_id', $visibleIds);
        }

        return $q->count();
    }

    /**
     * تعداد تیکت‌هایی (نه پیام‌هایی) که پیامِ خوانده‌نشده دارند — برای نشانِ منو.
     */
    public static function unreadTicketsCountFor(User $user): int
    {
        $q = static::unreadMessagesQuery($user);

        if (! $user->isSupportUser()) {
            $visibleIds = Ticket::visibleTo($user)->pluck('tickets.id');
            $q->whereIn('ticket_messages.ticket_id', $visibleIds);
        }

        return $q->distinct('ticket_messages.ticket_id')->count('ticket_messages.ticket_id');
    }

    /** پایهٔ کوئریِ پیام‌های خوانده‌نشده برای یک کاربر (بدونِ محدودهٔ تیکت). */
    private static function unreadMessagesQuery(User $user)
    {
        $q = TicketMessage::query()
            ->leftJoin('ticket_reads', function ($join) use ($user) {
                $join->on('ticket_reads.ticket_id', '=', 'ticket_messages.ticket_id')
                    ->where('ticket_reads.user_id', '=', $user->id);
            })
            ->where('ticket_messages.user_id', '!=', $user->id)
            ->where(function ($w) {
                $w->whereNull('ticket_reads.last_read_at')
                    ->orwhereColumn('ticket_messages.created_at', '>', 'ticket_reads.last_read_at');
            });

        // مشتری یادداشتِ داخلی را نمی‌بیند، پس خوانده‌نشده هم حساب نمی‌شود.
        if (! $user->isSupportUser()) {
            $q->where('ticket_messages.is_internal', false);
        }

        return $q;
    }
}
