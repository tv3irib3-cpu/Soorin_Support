<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * مشتری — یک شخص حقیقی یا حقوقی که خدمات پشتیبانی دریافت می‌کند.
 *
 * هر مشتری می‌تواند چند «پروژه» داشته باشد (مثال: شرکت آریا با پروژه‌های
 * بندرعباس، چابهار و بوشهر) و چند حساب کاربری.
 *
 * دسترسی‌های اینجا سقفِ سازمان را تعیین می‌کنند؛ حساب‌های کاربری فقط
 * می‌توانند از این سقف پایین‌تر بیایند، نه بالاتر.
 */
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BLOCKED   = 'blocked';

    /** پیش‌فرض روی خودِ مدل — وگرنه بلافاصله بعد از create() مقدار در حافظه null است. */
    protected $attributes = [
        'entity_type'         => 'company',
        'service_status'      => self::STATUS_ACTIVE,
        'can_create_ticket'   => true,
        'can_view_history'    => true,
        'can_view_invoices'   => true,
        'can_print_invoices'  => true,
    ];

    protected $fillable = [
        'code', 'name', 'entity_type', 'national_id', 'economic_code',
        'phone', 'mobile', 'email', 'city', 'address', 'postal_code',
        'service_status', 'suspension_message',
        'can_create_ticket', 'can_view_history', 'can_view_invoices',
        'can_print_invoices', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'can_create_ticket'  => 'boolean',
            'can_view_history'   => 'boolean',
            'can_view_invoices'  => 'boolean',
            'can_print_invoices' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------- روابط

    public function projects(): HasMany
    {
        return $this->hasMany(CustomerProject::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ------------------------------------------------------------- وضعیت

    /** آیا در حال حاضر می‌توان به این مشتری خدمات داد؟ */
    public function canReceiveService(): bool
    {
        return $this->service_status === self::STATUS_ACTIVE;
    }

    /**
     * پیامی که هنگام مسدود بودن به مشتری نشان داده می‌شود.
     * اگر مدیر پیام سفارشی ننوشته باشد، متن پیش‌فرض برمی‌گردد.
     */
    public function suspensionNotice(): string
    {
        return filled($this->suspension_message)
            ? $this->suspension_message
            : __('portal.default_suspension');
    }

    /** قرارداد معتبر در تاریخ داده‌شده (پیش‌فرض: امروز). */
    public function activeContract(?string $date = null): ?Contract
    {
        $date ??= now()->toDateString();

        return $this->contracts()
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->latest('start_date')
            ->first();
    }
}
