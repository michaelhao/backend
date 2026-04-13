<?php

namespace App\Models;

use App\Enums\GradeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'price', 'status'];

    protected function casts(): array
    {
        return [
            'status' => GradeStatus::class,
        ];
    }
}
