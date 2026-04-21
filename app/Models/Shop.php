<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'grade_id', 'sales_id', 'status', 'expired_at'];

    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
            'expired_at' => 'datetime',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function admin(): HasOne
    {
        return $this->hasOne(ShopAdmin::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ShopAddon::class);
    }
}
