<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAddonBalance extends Model
{
    protected $table = 'shop_addon_balances';

    protected $fillable = ['shop_id', 'addon_id', 'quantity', 'expired_at'];

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
