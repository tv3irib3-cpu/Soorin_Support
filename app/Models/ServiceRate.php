<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * نرخ پایه خدمات — برای صدور سریع فاکتور بدون تایپ دستی مبلغ.
 */
class ServiceRate extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'service_type', 'method', 'base_price', 'unit', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'is_active'  => 'boolean',
        ];
    }
}
