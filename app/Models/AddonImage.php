<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonImage extends Model
{
    protected $table = 'addons_image';

    protected $fillable = ['addon_id', 'image_url'];

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
