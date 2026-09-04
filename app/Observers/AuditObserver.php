<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * سیاههٔ عمومیِ حسابرسی — ساخت/ویرایش/حذفِ مدل‌های کلیدی (فاکتور، مشتری، قرارداد،
 * کاربر، پرداخت) را در activity_logs ثبت می‌کند: چه کسی، کِی، چه فیلدهایی از چه به چه.
 *
 * فیلدهای نویزی (زمان‌مُهرها، آخرین ورود) و حساس (رمز، توکن) ثبت نمی‌شوند، و شکستِ
 * ثبتِ سیاهه هرگز نباید خودِ عملیات (مثلِ صدور فاکتور) را از بین ببرد.
 */
class AuditObserver
{
    /** فیلدهایی که ثبتشان بی‌فایده یا حساس است. */
    private const IGNORE = [
        'updated_at', 'created_at', 'deleted_at',
        'password', 'remember_token',
        'last_login_at', 'last_login_ip',
    ];

    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    public function updated(Model $model): void
    {
        $changed = [];

        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, self::IGNORE, true)) {
                continue;
            }

            $changed[$key] = ['from' => $model->getOriginal($key), 'to' => $new];
        }

        // اگر فقط فیلدهای نویزی عوض شده باشند (مثلِ به‌روزرسانیِ آخرین ورود)، ثبت نکن.
        if ($changed === []) {
            return;
        }

        $this->log('updated', $model, $changed);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    /** ثبتِ امن — شکستش نباید عملیاتِ اصلی را بشکند. */
    private function log(string $action, Model $model, array $changes = []): void
    {
        try {
            ActivityLog::record($action, $model, $changes);
        } catch (\Throwable) {
            // نبودِ یک سطرِ سیاهه نباید مانعِ ذخیرهٔ دادهٔ اصلی شود.
        }
    }
}
