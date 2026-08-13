<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ثبت هر تغییر وضعیت تیکت — چه کسی، کِی، از چه وضعیتی به چه وضعیتی.
 * این رکوردها هرگز حذف نمی‌شوند.
 */
class TicketStatusLog extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'user_id', 'from_status', 'to_status'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
