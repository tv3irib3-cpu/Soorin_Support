<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\CustomerAccessLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceRead;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\TicketRead;
use App\Models\TicketStatusLog;
use Illuminate\Support\Facades\Storage;

/**
 * پاک‌سازیِ کاملِ دادهٔ عملیاتی/تستی — برای «رفتن به بهره‌برداری» پس از تست.
 *
 * فلسفه: هر چیزی که «فعالیت» است پاک می‌شود (تیکت، فاکتور، پرداخت، لاگ، پیوست)،
 * ولی «پیکربندی و موجودیت‌ها» می‌مانند تا سامانه آمادهٔ کار بماند:
 *   می‌ماند  → کاربران، مشتریان و پروژه/مخاطبِ آن‌ها، قراردادها و پلن‌ها،
 *              نرخِ خدمات، دسته‌بندیِ تیکت‌ها، تنظیمات و برند، لوگوها، پشتیبان‌ها.
 *   پاک می‌شود → تیکت‌ها + پیام‌ها + پیوست‌ها (فایل هم) + سوابقِ وضعیت/خوانده‌شدن،
 *                فاکتورها + اقلام + پرداخت‌ها + سوابقِ دیده‌شدن،
 *                لاگِ فعالیتِ مشتریان و تاریخچهٔ تغییرات.
 *
 * صداکردنِ این سرویس باید همیشه پس از گرفتنِ یک پشتیبانِ کامل باشد (صفحه این کار
 * را می‌کند). حذف‌ها گروهی‌اند؛ سهمِ سقفِ قراردادها هم دستی صفر می‌شود چون
 * فاکتورها گروهی حذف شده‌اند و رویدادِ مدلشان اجرا نمی‌شود.
 *
 * @return array<string, int>
 */
class DataResetService
{
    /**
     * @return array<string, int> شمارشِ آنچه پاک شد
     */
    public function purge(): array
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $disk   = Storage::disk('local');
        $counts = [];

        // ۱) پیوست‌ها: فایلِ هر ردیف را جدا حذف کن، بعد ردیف‌ها را.
        $attachmentCount = 0;
        TicketAttachment::query()->chunkById(200, function ($chunk) use ($disk, &$attachmentCount): void {
            foreach ($chunk as $att) {
                if ($att->path && $disk->exists($att->path)) {
                    $disk->delete($att->path);
                }
                $attachmentCount++;
            }
        });
        TicketAttachment::query()->delete();
        $counts['attachments'] = $attachmentCount;

        // ۲) فاکتور و وابسته‌هایش (فرزندان اول)
        $counts['payments'] = Payment::query()->count();
        Payment::query()->delete();
        InvoiceItem::query()->delete();
        InvoiceRead::query()->delete();
        $counts['invoices'] = Invoice::query()->count();
        Invoice::query()->delete();

        // ۳) تیکت و وابسته‌هایش (فرزندان اول)
        TicketStatusLog::query()->delete();
        TicketRead::query()->delete();
        TicketMessage::query()->delete();
        $counts['tickets'] = Ticket::query()->count();
        Ticket::query()->delete();

        // ۴) لاگ‌ها
        $counts['access_logs'] = CustomerAccessLog::query()->count();
        CustomerAccessLog::query()->delete();
        $counts['activity_logs'] = ActivityLog::query()->count();
        ActivityLog::query()->delete();

        // ۵) قراردادها می‌مانند؛ سهمِ مصرف‌شده‌شان (که از فاکتورها آمده بود) صفر می‌شود.
        Contract::query()->update(['used_amount' => 0]);

        return $counts;
    }
}
