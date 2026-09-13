<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * آخرین باری که هر کاربر فهرستِ فاکتورهای خود را دید — مبنای «فاکتورِ جدید».
 *
 * فاکتورِ جدید برای یک کاربر = فاکتوری از مشتریِ خودش که پس از آخرین بازدیدش
 * صادر شده و هنوز پیش‌نویس/لغوشده نیست.
 */
class InvoiceRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /** زمانِ آخرین بازدیدِ فهرستِ فاکتورها توسطِ کاربر (یا null اگر هرگز). */
    public static function lastSeenAt(User $user): ?Carbon
    {
        return static::where('user_id', $user->id)->value('last_seen_at');
    }

    /** ثبتِ اینکه کاربر همین حالا فهرستِ فاکتورها را دید. */
    public static function markSeen(User $user): void
    {
        static::updateOrCreate(['user_id' => $user->id], ['last_seen_at' => now()]);
    }

    /**
     * کوئریِ فاکتورهای «جدید» برای کاربر — فاکتورهای قابل‌دیدنِ مشتریِ او که پس از
     * آخرین بازدیدش صادر شده‌اند. اگر هرگز ندیده، همهٔ فاکتورهای قابل‌دیدنِ دارای
     * issued_at جدید حساب می‌شوند.
     */
    public static function newInvoicesQuery(User $user, ?Carbon $since = null)
    {
        $since ??= static::lastSeenAt($user);

        $query = Invoice::query()
            ->where('customer_id', $user->customer_id)
            ->whereIn('status', Invoice::VISIBLE_STATUSES)
            ->whereNotNull('issued_at');

        if ($since !== null) {
            $query->where('issued_at', '>', $since);
        }

        return $query;
    }

    /** تعدادِ فاکتورهای جدیدِ دیده‌نشده — برای نشانِ منو. */
    public static function newCountFor(User $user): int
    {
        if (! $user->isCustomerUser() || ! $user->customer_id || ! $user->canViewInvoices()) {
            return 0;
        }

        return static::newInvoicesQuery($user)->count();
    }
}
