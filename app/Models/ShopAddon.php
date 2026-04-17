<?php

namespace App\Models;

use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAddon extends Model
{
    protected $table = 'shops_addons';

    protected $fillable = ['shop_id', 'addon_id', 'source', 'status', 'expired_at'];

    protected function casts(): array
    {
        return [
            'source' => ShopAddonSource::class,
            'status' => ShopAddonStatus::class,
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
