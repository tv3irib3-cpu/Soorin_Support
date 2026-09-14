<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

/**
 * ثبتِ پاسخِ پشتیبان روی تیکت — یک‌جا و قابلِ تست (جدا از Filament).
 *
 * سه کار: ساختِ پیام، ذخیرهٔ پیوست‌ها، و افزودنِ «مدتِ کارکردِ این پاسخ» به
 * مجموعِ کارکردِ تیکت. تغییرِ خودکارِ وضعیت به «منتظر پاسخ مشتری» را
 * TicketMessageObserver هنگامِ ساختِ پیام انجام می‌دهد.
 */
class TicketReplyService
{
    public function __construct(private TicketAttachmentService $attachments)
    {
    }

    /**
     * @param  array{body: string, work_minutes?: int|string|null, is_internal?: bool, attachments?: array}  $data
     */
    public function reply(Ticket $ticket, User $author, array $data): TicketMessage
    {
        $message = TicketMessage::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $author->id,
            'body'        => $data['body'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        foreach ((array) ($data['attachments'] ?? []) as $file) {
            if ($file) {
                $this->attachments->store($file, $ticket, $message, $author);
            }
        }

        // مدتِ کارکرد به مجموعِ تیکت اضافه می‌شود. پیش از آن refresh تا وضعیتی که
        // TicketMessageObserver خودکار عوض کرده (منتظر پاسخ مشتری) از دست نرود.
        $ticket->refresh();
        $ticket->update([
            'work_minutes' => (int) $ticket->work_minutes + (int) ($data['work_minutes'] ?? 0),
        ]);

        return $message;
    }
}
