<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    /** @use HasFactory<\Database\Factories\GameFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'home_team_id',
        'away_team_id',
        'court',
        'starts_at',
        'ends_at',
        'home_goals',
        'away_goals',
        'is_finalized',
        'unfinalize_count',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_finalized' => 'boolean',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function finalize(): void
    {
        $this->update(['is_finalized' => true]);
    }

    public function unfinalize(): void
    {
        $this->update([
            'is_finalized' => false,
            'home_goals' => null,
            'away_goals' => null,
            'unfinalize_count' => $this->unfinalize_count + 1,
        ]);
    }

    public function canBeUnfinalized(): bool
    {
        return $this->unfinalize_count < 1;
    }

    public function hasResult(): bool
    {
        return $this->home_goals !== null && $this->away_goals !== null;
    }
}
