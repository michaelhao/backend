<?php

namespace App\Models;

use App\Enums\ConferenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conference extends Model
{
    use HasFactory;

    public const PER_PAGE_OPTIONS = [50, 100, 150, 200];

    public const DEFAULT_PER_PAGE = 50;

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
