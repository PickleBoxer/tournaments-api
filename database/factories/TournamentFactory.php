<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tournament>
 */
class TournamentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'start_datetime' => fake()->dateTimeBetween('now', '+1 month'),
            'match_duration_minutes' => fake()->numberBetween(15, 30),
            'num_courts' => fake()->numberBetween(1, 4),
        ];
    }
}
