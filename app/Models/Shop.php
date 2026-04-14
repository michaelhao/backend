<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'grade_id', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(ShopAdmin::class);
    }
}
