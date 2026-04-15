<?php

namespace App\Models;

use App\Enums\AddonStatus;
use App\Enums\AddonSyncing;
use App\Enums\AddonType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'name', 'price', 'unit', 'status', 'syncing'];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'status' => AddonStatus::class,
            'syncing' => AddonSyncing::class,
        ];
    }

    public function image(): HasOne
    {
        return $this->hasOne(AddonImage::class);
    }

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'grades_addons')->withTimestamps();
    }

    public function shops(): HasMany
    {
        return $this->hasMany(ShopAddon::class);
    }
}
