<?php

namespace App\Models;

use App\Enums\BillPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillStatusLog extends Model
{
    protected $table = 'bills_status_logs';

    const UPDATED_AT = null;

    protected $fillable = [
        'bill_id',
        'from_status',
        'to_status',
        'operator_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => BillPaymentStatus::class,
            'to_status' => BillPaymentStatus::class,
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
