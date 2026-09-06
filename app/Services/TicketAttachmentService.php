<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ذخیرهٔ پیوستِ تیکت با یک «کدِ اختصاصی» به‌عنوان نامِ فایل.
 *
 * قالبِ کد: {کدِ مشتری}-{تاریخِ شمسی}-{ساعت}-{۴ نویسهٔ یکتا}
 *   مثال: ARIA-BUS-14050615-143210-a1b2.pdf
 *
 * چرا؟ مالک خواست فایل‌ها روی هاست با نامی که شاملِ کدِ مشتری و تاریخ/ساعت است
 * ذخیره شوند تا برای پشتیبان‌گیری و پیگیری به‌راحتی پیدا شوند. نامِ اصلیِ فایل
 * جدا در ستونِ original_name نگه داشته می‌شود تا هنگام دانلود به کاربر نشان
 * داده شود.
 *
 * فایل‌ها در storage/app/ticket-attachments (خارج از وب‌روت) می‌مانند؛ دسترسی
 * فقط از مسیرِ دانلودِ کنترل‌شده. این پوشه در بسته‌های به‌روزرسانی دست‌نخورده
 * می‌ماند (copyOver پوشهٔ storage را رد می‌کند).
 */
class TicketAttachmentService
{
    public const DISK = 'local';
    public const DIR  = 'ticket-attachments';

    /** بیشینهٔ حجمِ هر فایل: ۵۰ مگابایت (بر حسب کیلوبایت برای قانونِ اعتبارسنجی). */
    public const MAX_KB = 51200;

    /** پسوندهای مجاز — تصویر، ویدئو و PDF. */
    public const ALLOWED_EXT = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v',
        'pdf',
    ];

    /** قانونِ اعتبارسنجیِ آرایهٔ فایل‌ها برای فرم‌ها. */
    public static function validationRule(): array
    {
        return ['file', 'max:' . self::MAX_KB, 'mimes:' . implode(',', self::ALLOWED_EXT)];
    }

    public function store(UploadedFile $file, Ticket $ticket, ?TicketMessage $message, User $user): TicketAttachment
    {
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $code = $this->code($ticket, $ext);

        $path = Storage::disk(self::DISK)->putFileAs(self::DIR, $file, $code);

        return TicketAttachment::create([
            'ticket_id'         => $ticket->id,
            'ticket_message_id' => $message?->id,
            'user_id'           => $user->id,
            'path'              => $path,
            'original_name'     => $file->getClientOriginalName(),
            'mime'              => $file->getClientMimeType(),
            'size'              => $file->getSize(),
        ]);
    }

    /** کدِ یکتای فایل بر پایهٔ کدِ مشتری + تاریخ/ساعتِ شمسی. */
    private function code(Ticket $ticket, string $ext): string
    {
        $customerCode = $ticket->customer?->code ?: ('C' . $ticket->customer_id);
        $customerCode = preg_replace('/[^A-Za-z0-9\-_]/', '', $customerCode) ?: 'CUST';

        $stamp = Verta::now()->format('Ymd-His');
        $rand  = Str::lower(Str::random(4));

        return "{$customerCode}-{$stamp}-{$rand}.{$ext}";
    }
}
