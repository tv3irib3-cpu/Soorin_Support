<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * تیکت پشتیبانی.
 *
 * قاعده ثابت پروژه: تیکت بسته‌شده **قفل می‌شود ولی هرگز حذف نمی‌شود** —
 * گزارش‌های تاریخی به آن وابسته‌اند.
 */
class Ticket extends Model
{
    use HasFactory;

    public const STATUS_NEW              = 'new';
    public const STATUS_IN_PROGRESS      = 'in_progress';
    public const STATUS_WAITING_CUSTOMER = 'waiting_customer';
    public const STATUS_WAITING_PAYMENT  = 'waiting_payment';
    public const STATUS_RESOLVED         = 'resolved';
    public const STATUS_CLOSED           = 'closed';
    public const STATUS_CANCELLED        = 'cancelled';

    /**
     * چرخه مجاز وضعیت. هر تغییری که در این نقشه نباشد رد می‌شود.
     *
     *   جدید → در حال بررسی → منتظر پاسخ مشتری → منتظر پرداخت → حل‌شده → بسته‌شده
     *                                                              ↘ لغوشده
     */
    public const TRANSITIONS = [
        self::STATUS_NEW              => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS      => [self::STATUS_WAITING_CUSTOMER, self::STATUS_WAITING_PAYMENT, self::STATUS_RESOLVED, self::STATUS_CANCELLED],
        self::STATUS_WAITING_CUSTOMER => [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CANCELLED],
        self::STATUS_WAITING_PAYMENT  => [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CANCELLED],
        self::STATUS_RESOLVED         => [self::STATUS_CLOSED, self::STATUS_IN_PROGRESS],
        self::STATUS_CLOSED           => [],   // پایان راه — قفل می‌شود
        self::STATUS_CANCELLED        => [],
    ];

    protected $fillable = [
        'number', 'customer_id', 'customer_project_id', 'ticket_category_id',
        'system_name', 'contract_id', 'subject', 'description',
        'service_type', 'method', 'priority', 'status',
        'assigned_to', 'created_by', 'work_minutes', 'resolution',
        'first_response_at', 'resolved_at', 'closed_at', 'is_locked',
        'rating', 'rating_comment',
    ];

    protected function casts(): array
    {
        return [
            'is_locked'         => 'boolean',
            'first_response_at' => 'datetime',
            'resolved_at'       => 'datetime',
            'closed_at'         => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- روابط

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CustomerProject::class, 'customer_project_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /** پیام‌هایی که مشتری اجازه دیدنشان را دارد — یادداشت داخلی حذف می‌شود. */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // -------------------------------------------------------- چرخه وضعیت

    /** آیا تغییر وضعیت به مقصد داده‌شده مجاز است؟ */
    public function canTransitionTo(string $status): bool
    {
        if ($this->is_locked) {
            return false;
        }

        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** وضعیت‌هایی که از وضعیت فعلی می‌توان به آن‌ها رفت. */
    public function availableTransitions(): array
    {
        return $this->is_locked ? [] : (self::TRANSITIONS[$this->status] ?? []);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_CLOSED, self::STATUS_CANCELLED], true);
    }

    // -------------------------------------------------------------- کوئری‌ها

    /**
     * محدود کردن تیکت‌ها به آنچه این کاربر حق دیدنش را دارد.
     *
     * کاربران داخلی همه را می‌بینند. کاربران مشتری بر اساس دامنه دسترسی:
     *   none      → هیچ (کوئری خالی برمی‌گردد)
     *   own       → فقط تیکت‌هایی که خودش ثبت کرده
     *   project   → تیکت‌های پروژه‌های تخصیص‌داده‌شده
     *   customer  → تمام تیکت‌های آن مشتری
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSupportUser()) {
            return $query;
        }

        // کاربر مشتری هرگز نباید داده مشتری دیگر را ببیند — این شرط همیشه اعمال می‌شود
        $query->where('customer_id', $user->customer_id);

        return match ($user->historyScope()) {
            'customer' => $query,
            'project'  => $query->whereIn('customer_project_id', $user->accessibleProjectIds()),
            'own'      => $query->where('created_by', $user->id),
            default    => $query->whereRaw('1 = 0'),   // none — هیچ سابقه‌ای
        };
    }
}
