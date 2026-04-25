<?php

namespace App\Models;

use App\Enums\ConferenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conference extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'started_at',
        'ended_at',
        'register_started_at',
        'register_ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConferenceStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'register_started_at' => 'datetime',
            'register_ended_at' => 'datetime',
        ];
    }
}
