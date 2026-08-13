<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * پیام در گفتگوی تیکت.
 *
 * is_internal = true یعنی یادداشت داخلی — مشتری آن را **هرگز** نمی‌بیند.
 * هر جا پیام‌ها را به مشتری نشان می‌دهی، از Ticket::publicMessages استفاده کن.
 */
class TicketMessage extends Model
{
    use HasFactory;

    protected $attributes = ['is_internal' => false];

    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
