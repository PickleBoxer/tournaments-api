<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+1 week');
        $endsAt = clone $startsAt;
        $endsAt->modify('+20 minutes');

        return [
            'tournament_id' => Tournament::factory(),
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'court' => fake()->numberBetween(1, 4),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'home_goals' => null,
            'away_goals' => null,
            'is_finalized' => false,
            'unfinalize_count' => 0,
        ];
    }
}
