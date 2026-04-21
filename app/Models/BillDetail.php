<?php

namespace App\Models;

use App\Enums\BillDetailPaymentType;
use App\Enums\BillDetailType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillDetail extends Model
{
    use HasFactory;

    protected $table = 'bills_details';

    protected $fillable = [
        'bill_id',
        'type',
        'payment_type',
        'quantity',
        'unit_price',
        'total_price',
        'name',
        'start_at',
        'expired_at',
        'total_months',
        'is_effective',
        'canceled_at',
        'canceled_by',
        'applied_at',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'type' => BillDetailType::class,
            'payment_type' => BillDetailPaymentType::class,
            'start_at' => 'datetime',
            'expired_at' => 'datetime',
            'canceled_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function futureEffect(): HasOne
    {
        return $this->hasOne(BillFutureEffect::class, 'bill_detail_id');
    }
}
