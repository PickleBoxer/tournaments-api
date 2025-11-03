<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'start_datetime',
        'match_duration_minutes',
        'num_courts',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function hasAnyFinalizedGames(): bool
    {
        return $this->games()->where('is_finalized', true)->exists();
    }

    public function canRegenerateSchedule(): bool
    {
        return ! $this->hasAnyFinalizedGames();
    }
}
