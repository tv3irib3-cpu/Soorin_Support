<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'ticket_message_id', 'user_id',
        'path', 'original_name', 'mime', 'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** حجم فایل به‌صورت خوانا — مثلاً «۱٫۴ مگابایت» */
    public function humanSize(): string
    {
        $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        $size  = (float) $this->size;
        $i     = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return number_format($size, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
