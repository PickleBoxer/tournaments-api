<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Services\ScheduleGeneratorService;

test('property: all games have different home and away teams', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    // Test with various team counts
    $teamCounts = [4, 5, 6, 8, 10];

    foreach ($teamCounts as $count) {
        // Clear previous teams and games
        $tournament->games()->delete();
        $tournament->teams()->delete();

        // Create teams
        for ($i = 0; $i < $count; $i++) {
            $tournament->teams()->create(['name' => "Team {$i} Count {$count}"]);
        }

        // Generate schedule
        $service = new ScheduleGeneratorService;
        $service->generate($tournament);

        // Verify invariant: no team plays itself
        $games = Game::where('tournament_id', $tournament->id)->get();

        foreach ($games as $game) {
            expect($game->home_team_id)
                ->not->toBe($game->away_team_id,
                    "Self-game detected for team count {$count}: Game ID {$game->id}");
        }
    }
});

test('property: no team plays concurrent games', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 3,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    // Test with various team counts
    $teamCounts = [6, 7, 8];

    foreach ($teamCounts as $count) {
        // Clear previous teams and games
        $tournament->games()->delete();
        $tournament->teams()->delete();

        // Create teams
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = $tournament->teams()->create(['name' => "Team {$i} Count {$count}"]);
        }

        // Generate schedule
        $service = new ScheduleGeneratorService;
        $service->generate($tournament);

        // Verify invariant: no concurrent games per team
        foreach ($teams as $team) {
            $teamGames = Game::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($team): void {
                    $query->where('home_team_id', $team->id)
                        ->orWhere('away_team_id', $team->id);
                })
                ->orderBy('starts_at')
                ->get();

            for ($i = 0; $i < $teamGames->count() - 1; $i++) {
                $currentGame = $teamGames[$i];
                $nextGame = $teamGames[$i + 1];

                expect($currentGame->ends_at->lte($nextGame->starts_at))
                    ->toBeTrue(
                        "Concurrent games detected for Team {$team->id} (count {$count}): ".
                        "Game {$currentGame->id} ends at {$currentGame->ends_at}, ".
                        "Game {$nextGame->id} starts at {$nextGame->starts_at}"
                    );
            }
        }
    }
});

test('property: all finalized games have non-negative goals', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Team A']);
    $team2 = $tournament->teams()->create(['name' => 'Team B']);
    $team3 = $tournament->teams()->create(['name' => 'Team C']);

    // Create finalized games with various scores
    $games = [
        ['home' => $team1->id, 'away' => $team2->id, 'home_goals' => 0, 'away_goals' => 0],
        ['home' => $team1->id, 'away' => $team3->id, 'home_goals' => 5, 'away_goals' => 3],
        ['home' => $team2->id, 'away' => $team3->id, 'home_goals' => 1, 'away_goals' => 1],
        ['home' => $team1->id, 'away' => $team2->id, 'home_goals' => 10, 'away_goals' => 0],
    ];

    foreach ($games as $gameData) {
        Game::create([
            'tournament_id' => $tournament->id,
            'home_team_id' => $gameData['home'],
            'away_team_id' => $gameData['away'],
            'court' => 1,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'home_goals' => $gameData['home_goals'],
            'away_goals' => $gameData['away_goals'],
            'is_finalized' => true,
        ]);
    }

    // Verify invariant: all finalized games have non-negative goals
    $finalizedGames = Game::where('tournament_id', $tournament->id)
        ->where('is_finalized', true)
        ->get();

    foreach ($finalizedGames as $game) {
        expect($game->home_goals)
            ->toBeGreaterThanOrEqual(0, "Game {$game->id} has negative home_goals: {$game->home_goals}");

        expect($game->away_goals)
            ->toBeGreaterThanOrEqual(0, "Game {$game->id} has negative away_goals: {$game->away_goals}");
    }
});

test('property: round robin generates correct number of games', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    // For odd teams, byes are created (null away_team_id)
    // For even teams, standard round-robin: n(n-1)/2
    $testCases = [
        ['teams' => 4, 'expected_games' => 6],    // 4*3/2 = 6 (even, no byes)
        ['teams' => 5, 'expected_games' => 15],   // adds null team, 6*5/2 = 15 (with byes)
        ['teams' => 6, 'expected_games' => 15],   // 6*5/2 = 15 (even, no byes)
        ['teams' => 8, 'expected_games' => 28],   // 8*7/2 = 28 (even, no byes)
    ];

    foreach ($testCases as $testCase) {
        // Clear previous teams and games
        $tournament->games()->delete();
        $tournament->teams()->delete();

        // Create teams
        for ($i = 0; $i < $testCase['teams']; $i++) {
            $tournament->teams()->create(['name' => "Team {$i} Count {$testCase['teams']}"]);
        }

        // Generate schedule
        $service = new ScheduleGeneratorService;
        $service->generate($tournament);

        $games = Game::where('tournament_id', $tournament->id)->count();

        expect($games)->toBe(
            $testCase['expected_games'],
            "For {$testCase['teams']} teams, expected {$testCase['expected_games']} games but got {$games}"
        );
    }
});

test('property: each team plays exactly n-1 games in round robin', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $teamCounts = [4, 5, 6, 7, 8];

    foreach ($teamCounts as $count) {
        // Clear previous teams and games
        $tournament->games()->delete();
        $tournament->teams()->delete();

        // Create teams
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $teams[] = $tournament->teams()->create(['name' => "Team {$i} Count {$count}"]);
        }

        // Generate schedule
        $service = new ScheduleGeneratorService;
        $service->generate($tournament);

        // For even teams: each team plays n-1 games
        // For odd teams: each team appears in n games total (n-1 real + 1 bye with null away_team_id)
        $expectedGames = ($count % 2 === 0) ? $count - 1 : $count;

        // Verify each team appears in correct number of games
        foreach ($teams as $team) {
            $teamGames = Game::where('tournament_id', $tournament->id)
                ->where(function ($query) use ($team): void {
                    $query->where('home_team_id', $team->id)
                        ->orWhere('away_team_id', $team->id);
                })
                ->count();

            expect($teamGames)->toBe(
                $expectedGames,
                "Team {$team->id} in {$count}-team tournament should appear in {$expectedGames} games but appeared in {$teamGames}"
            );
        }
    }
});
