<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillFutureEffect extends Model
{
    protected $table = 'bills_future_effect';

    protected $fillable = [
        'bill_id',
        'bill_detail_id',
        'execute_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'execute_at' => 'date',
            'finished_at' => 'date',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(BillDetail::class, 'bill_detail_id');
    }
}
