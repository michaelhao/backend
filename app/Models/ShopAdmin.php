<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopAdmin extends Model
{
    use HasFactory;

    protected $table = 'shops_admin';

    protected $fillable = ['shop_id', 'name', 'email', 'password', 'business_number', 'company_name'];

    protected function casts(): array
    {
        return [
            'email' => 'encrypted',
            'password' => 'hashed',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
