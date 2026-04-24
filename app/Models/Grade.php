<?php

namespace App\Models;

use App\Enums\GradeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'price', 'weight', 'status'];

    protected function casts(): array
    {
        return [
            'status' => GradeStatus::class,
        ];
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'grades_addons')->withTimestamps();
    }
}
