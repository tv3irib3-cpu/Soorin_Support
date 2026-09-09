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

    /** پسوندِ فایل از روی نامِ ذخیره‌شده (کوچک). */
    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    /** آیا عکس است؟ از mime یا پسوند تشخیص می‌دهد (mime گاهی درست ثبت نمی‌شود). */
    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/')
            || in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    /** آیا ویدئو است؟ */
    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime, 'video/')
            || in_array($this->extension(), ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v'], true);
    }

    /** بهترین Content-Type برای نمایش — اگر mime ثبت‌نشده/عمومی بود، از پسوند حدس می‌زند. */
    public function displayMime(): string
    {
        if (filled($this->mime) && $this->mime !== 'application/octet-stream') {
            return $this->mime;
        }

        return match ($this->extension()) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            'mp4', 'm4v'  => 'video/mp4',
            'webm'        => 'video/webm',
            'mov'         => 'video/quicktime',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };
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
