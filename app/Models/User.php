<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * کاربر سامانه.
 *
 * چهار نوع حساب وجود دارد و تنها «مدیر پشتیبان» اجازه ساخت حساب دارد:
 *   support_admin   مدیر پشتیبان    — دسترسی کامل
 *   support_staff   کارشناس پشتیبان — کار روی تیکت، بدون ساخت حساب
 *   customer_admin  مدیر مشتری      — تمام پروژه‌های همان مشتری
 *   customer_staff  کارشناس مشتری   — فقط پروژه‌های تخصیص‌داده‌شده به او
 *
 * دسترسی کاربر مشتری از دو لایه خوانده می‌شود:
 *   لایه اول  — سطح سازمان: فیلدهای جدول customers
 *   لایه دوم  — سطح حساب:   فیلدهای همین جدول (فقط محدودتر می‌کنند، بازتر نه)
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    public const TYPE_SUPPORT_ADMIN  = 'support_admin';
    public const TYPE_SUPPORT_STAFF  = 'support_staff';
    public const TYPE_CUSTOMER_ADMIN = 'customer_admin';
    public const TYPE_CUSTOMER_STAFF = 'customer_staff';

    protected $fillable = [
        'name', 'email', 'mobile', 'password', 'user_type', 'customer_id',
        'theme', 'is_active', 'can_create_ticket', 'can_view_invoices',
        'can_print_invoices', 'history_scope',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * مقادیر پیش‌فرض روی خودِ مدل — نه فقط در دیتابیس.
     * بدون این، رکورد تازه‌ساخته‌شده تا وقتی از دیتابیس خوانده نشود
     * is_active برابر null دارد و بررسی‌های دسترسی اشتباه جواب می‌دهند.
     */
    protected $attributes = [
        'is_active' => true,
        'theme'     => 'ocean',
        'user_type' => self::TYPE_SUPPORT_STAFF,
    ];

    protected function casts(): array
    {
        return [
            'password'           => 'hashed',
            'is_active'          => 'boolean',
            'can_create_ticket'  => 'boolean',
            'can_view_invoices'  => 'boolean',
            'can_print_invoices' => 'boolean',
            'last_login_at'      => 'datetime',
        ];
    }

    /**
     * دسترسی به پنل Filament.
     *
     * فقط کاربران داخلی و فعال. این بررسی مستقل از میان‌افزار انجام می‌شود
     * تا حتی اگر ترتیب میان‌افزارها عوض شد، کاربر مشتری وارد پنل نشود.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSupportUser() && $this->is_active;
    }

    protected static function booted(): void
    {
        /*
        | نقش Spatie همیشه با user_type هم‌راستا نگه داشته می‌شود.
        |
        | بدون این، کاربری که مدیر از پنل می‌سازد هیچ نقشی نمی‌گیرد و چون
        | تمام بررسی‌های دسترسی مجوزمحورند، بعد از ورود پنل کاملاً خالی
        | می‌بیند. تغییر نوع حساب هم باید نقش را جابه‌جا کند.
        |
        | در مدل انجام شده (نه observer) تا از هر مسیری — پنل، seeder،
        | tinker، تست — یکسان اجرا شود.
        */
        static::saved(function (User $user) {
            if (blank($user->user_type) || ! $user->wasChanged('user_type') && ! $user->wasRecentlyCreated) {
                return;
            }

            // اگر نقش‌ها هنوز seed نشده‌اند (دیتابیس تازه)، بی‌صدا رد شود
            if (! Role::where('name', $user->user_type)->where('guard_name', 'web')->exists()) {
                return;
            }

            $user->syncRoles([$user->user_type]);
        });
    }

    // ---------------------------------------------------------------- روابط

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** پروژه‌هایی که این کارشناس مشتری مسئولشان است. */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(CustomerProject::class, 'customer_project_user')
            ->withTimestamps();
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    // ------------------------------------------------------- تشخیص نوع حساب

    public function isSupportAdmin(): bool
    {
        return $this->user_type === self::TYPE_SUPPORT_ADMIN;
    }

    public function isSupportStaff(): bool
    {
        return $this->user_type === self::TYPE_SUPPORT_STAFF;
    }

    /** کاربر داخلی شرکت — به پنل مدیریت دسترسی دارد. */
    public function isSupportUser(): bool
    {
        return in_array($this->user_type, [
            self::TYPE_SUPPORT_ADMIN,
            self::TYPE_SUPPORT_STAFF,
        ], true);
    }

    public function isCustomerAdmin(): bool
    {
        return $this->user_type === self::TYPE_CUSTOMER_ADMIN;
    }

    public function isCustomerStaff(): bool
    {
        return $this->user_type === self::TYPE_CUSTOMER_STAFF;
    }

    /** کاربر سمت مشتری — فقط به پرتال دسترسی دارد. */
    public function isCustomerUser(): bool
    {
        return in_array($this->user_type, [
            self::TYPE_CUSTOMER_ADMIN,
            self::TYPE_CUSTOMER_STAFF,
        ], true);
    }

    // ------------------------------------------------------------- دسترسی‌ها

    /**
     * شناسه پروژه‌هایی که این کاربر حق دیدنشان را دارد.
     *
     *   مدیر مشتری  → همه پروژه‌های آن مشتری
     *   کارشناس مشتری → فقط پروژه‌های تخصیص‌داده‌شده
     *
     * @return array<int>
     */
    public function accessibleProjectIds(): array
    {
        if (! $this->isCustomerUser() || ! $this->customer_id) {
            return [];
        }

        if ($this->isCustomerAdmin()) {
            return $this->customer->projects()->pluck('id')->all();
        }

        return $this->projects()->pluck('customer_projects.id')->all();
    }

    /**
     * آیا این کاربر اجازه ثبت تیکت دارد؟
     *
     * سه شرط پشت سر هم بررسی می‌شود و هر کدام «نه» بگوید، پاسخ نه است:
     *   ۱. وضعیت خدمات‌دهی مشتری فعال باشد
     *   ۲. مشتری در سطح سازمان اجازه ثبت تیکت داشته باشد
     *   ۳. خودِ این حساب محدود نشده باشد
     */
    public function canCreateTicket(): bool
    {
        if ($this->isSupportUser()) {
            return true;
        }

        if (! $this->customer || ! $this->customer->canReceiveService()) {
            return false;
        }

        if (! $this->customer->can_create_ticket) {
            return false;
        }

        // null یعنی «محدودیتی در سطح حساب تعریف نشده» → از سطح سازمان پیروی کن
        return $this->can_create_ticket ?? true;
    }

    /**
     * دامنه دیدن سوابق تیکت.
     *
     * لایه اول (سقف سازمان): اگر مشتری در سطح سازمان اجازه دیدن تاریخچه
     * نداشته باشد، هیچ حسابی زیرمجموعه‌اش سابقه نمی‌بیند — حتی مدیر مشتری.
     * این همان سناریوی صریح مالک پروژه است: «دسترسی مشتری به تاریخچه بسته
     * باشد ولی فقط بتواند تیکت جدید بزند».
     *
     * لایه دوم (سطح حساب): اگر تعیین نشده باشد، پیش‌فرض نقش اعمال می‌شود.
     */
    public function historyScope(): string
    {
        if ($this->isSupportUser()) {
            return 'all';
        }

        if (! $this->customer?->can_view_history) {
            return 'none';
        }

        if ($this->history_scope !== null) {
            return $this->history_scope;
        }

        // پیش‌فرض: مدیر مشتری همه‌چیز، کارشناس مشتری هیچ سابقه‌ای
        return $this->isCustomerAdmin() ? 'customer' : 'none';
    }

    public function canViewInvoices(): bool
    {
        if ($this->isSupportUser()) {
            return true;
        }

        if (! $this->customer?->can_view_invoices) {
            return false;
        }

        // پیش‌فرض: فقط مدیر مشتری فاکتور می‌بیند
        return $this->can_view_invoices ?? $this->isCustomerAdmin();
    }

    public function canPrintInvoices(): bool
    {
        if ($this->isSupportUser()) {
            return true;
        }

        if (! $this->canViewInvoices() || ! $this->customer?->can_print_invoices) {
            return false;
        }

        return $this->can_print_invoices ?? $this->isCustomerAdmin();
    }
}
