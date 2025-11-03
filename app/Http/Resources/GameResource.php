<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Game
 *
 * @property-read \App\Models\Team $homeTeam
 * @property-read \App\Models\Team|null $awayTeam
 */
class GameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'match_id' => $this->id,
            'home_team' => $this->homeTeam->name,
            'away_team' => $this->awayTeam !== null ? $this->awayTeam->name : 'BYE',
            'court' => $this->court,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
        ];
    }
}
