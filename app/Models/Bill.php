<?php

namespace App\Models;

use App\Enums\BillPaymentMethod;
use App\Enums\BillPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'no',
        'creator_id',
        'shop_id',
        'shop_sales_id',
        'total',
        'total_grade',
        'total_addons',
        'discount_amount',
        'payment_status',
        'payment_method',
        'paid_at',
        'invoice_no',
    ];

    protected function casts(): array
    {
        return [
            'payment_status' => BillPaymentStatus::class,
            'payment_method' => BillPaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function shopSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shop_sales_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(BillDetail::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BillStatusLog::class);
    }
}
