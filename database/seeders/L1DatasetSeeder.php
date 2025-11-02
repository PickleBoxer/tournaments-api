<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\ScheduleGeneratorService;
use Illuminate\Database\Seeder;

class L1DatasetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create tournament
        $tournament = Tournament::create([
            'name' => 'L1 Test Tournament',
            'start_datetime' => '2025-04-01T09:00:00',
            'match_duration_minutes' => 20,
            'num_courts' => 2,
        ]);

        // 2. Create teams
        $teams = ['A', 'B', 'C', 'D'];
        foreach ($teams as $name) {
            Team::create([
                'tournament_id' => $tournament->id,
                'name' => $name,
            ]);
        }

        // 3. Generate schedule
        app(ScheduleGeneratorService::class)->generate($tournament);

        // 4. Submit results
        // Format: [team1, team2, team1_goals, team2_goals]
        $results = [
            ['A', 'B', 1, 0],
            ['B', 'C', 2, 1],
            ['C', 'A', 3, 2],
            ['A', 'D', 5, 0],
            ['B', 'D', 4, 1],
            ['C', 'D', 2, 2],
        ];

        foreach ($results as [$team1, $team2, $team1Goals, $team2Goals]) {
            // Try to find the game with either team order (A vs B or B vs A)
            $game = Game::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($team1, $team2) {
                    // Match: team1 home, team2 away
                    $query->where(function ($q) use ($team1, $team2) {
                        $q->whereHas('homeTeam', fn($qt) => $qt->where('name', $team1))
                          ->whereHas('awayTeam', fn($qt) => $qt->where('name', $team2));
                    })
                    // OR Match: team2 home, team1 away
                    ->orWhere(function ($q) use ($team1, $team2) {
                        $q->whereHas('homeTeam', fn($qt) => $qt->where('name', $team2))
                          ->whereHas('awayTeam', fn($qt) => $qt->where('name', $team1));
                    });
                })
                ->first();

            if (!$game) {
                $this->command->warn("⚠️  Game not found: {$team1} vs {$team2}");
                continue;
            }

            // Determine correct goal assignment based on actual home/away order
            $homeTeamName = $game->homeTeam->name;

            if ($homeTeamName === $team1) {
                // Game is stored as team1(home) vs team2(away)
                $homeGoals = $team1Goals;
                $awayGoals = $team2Goals;
            } else {
                // Game is stored as team2(home) vs team1(away) - swap goals
                $homeGoals = $team2Goals;
                $awayGoals = $team1Goals;
            }

            $game->update([
                'home_goals' => $homeGoals,
                'away_goals' => $awayGoals,
                'is_finalized' => true,
            ]);

            $this->command->info("✓ Updated: {$game->homeTeam->name} {$homeGoals}-{$awayGoals} {$game->awayTeam->name}");
        }

        $this->command->info("\n✅ L1 Dataset seeded successfully!");
        $this->command->info("Tournament: {$tournament->name}");
        $this->command->info("Teams: " . implode(', ', $teams));
        $this->command->info("Games finalized: " . $tournament->games()->where('is_finalized', true)->count());
    }
}
