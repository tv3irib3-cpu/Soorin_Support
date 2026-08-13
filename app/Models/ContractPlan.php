<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نوع قرارداد — طلایی، نقره‌ای، برنزی یا هر نوع دلخواه دیگر.
 *
 * هر نوع برای چهار حوزه یک درصد پوشش دارد. مثال قرارداد طلایی:
 *   نرم‌افزاری ۱۰۰٪ · سخت‌افزاری ۷۰٪ · قطعات ۵۰٪ · اعزام کارشناس ۱۰۰٪
 */
class ContractPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'color', 'description',
        'cover_software', 'cover_hardware', 'cover_parts', 'cover_onsite',
        'ceiling_amount', 'included_tickets', 'response_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cover_software' => 'integer',
            'cover_hardware' => 'integer',
            'cover_parts'    => 'integer',
            'cover_onsite'   => 'integer',
            'ceiling_amount' => 'integer',
            'is_active'      => 'boolean',
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * درصد پوششی که این قرارداد برای یک ردیف فاکتور می‌دهد.
     *
     * @param  string  $itemType     service | part | other
     * @param  string  $serviceType  software | hardware | other
     * @param  ?string $method       phone | remote | onsite
     */
    public function coverPercentFor(string $itemType, string $serviceType, ?string $method = null): int
    {
        // قطعه همیشه از نرخ «پوشش قطعات» استفاده می‌کند، فارغ از نوع خدمت
        if ($itemType === 'part') {
            return $this->cover_parts;
        }

        // اعزام حضوری نرخ جدا دارد و بر نوع خدمت مقدم است
        if ($method === 'onsite') {
            return $this->cover_onsite;
        }

        return match ($serviceType) {
            'software' => $this->cover_software,
            'hardware' => $this->cover_hardware,
            default    => 0,
        };
    }
}
