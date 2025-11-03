<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Services\ScheduleGeneratorService;

test('never creates self games', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $teams = [];
    for ($i = 0; $i < 6; $i++) {
        $teams[] = $tournament->teams()->create(['name' => "Team {$i}"]);
    }

    $service = new ScheduleGeneratorService();
    $service->generate($tournament);

    $games = Game::where('tournament_id', $tournament->id)->get();

    foreach ($games as $game) {
        expect($game->home_team_id)->not->toBe($game->away_team_id);
    }
});

test('no concurrent games per team', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $teams = [];
    for ($i = 0; $i < 6; $i++) {
        $teams[] = $tournament->teams()->create(['name' => "Team {$i}"]);
    }

    $service = new ScheduleGeneratorService();
    $service->generate($tournament);

    foreach ($teams as $team) {
        $teamGames = Game::where('tournament_id', $tournament->id)
            ->where(function ($query) use ($team) {
                $query->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            })
            ->orderBy('starts_at')
            ->get();

        for ($i = 0; $i < $teamGames->count() - 1; $i++) {
            $currentGame = $teamGames[$i];
            $nextGame = $teamGames[$i + 1];

            expect($currentGame->ends_at->lte($nextGame->starts_at))
                ->toBeTrue("Team {$team->id} has overlapping games");
        }
    }
});

test('handles odd number teams with byes', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $teams = [];
    for ($i = 0; $i < 5; $i++) {
        $teams[] = $tournament->teams()->create(['name' => "Team {$i}"]);
    }

    $service = new ScheduleGeneratorService();
    $service->generate($tournament);

    $games = Game::where('tournament_id', $tournament->id)->get();

    // For 5 teams with byes: 15 total games (some with null away_team_id)
    expect($games->count())->toBe(15);

    // Each team should appear in exactly 5 games (4 real opponents + 1 bye)
    foreach ($teams as $team) {
        $teamGames = Game::where('tournament_id', $tournament->id)
            ->where(function ($query) use ($team) {
                $query->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            })
            ->count();

        expect($teamGames)->toBe(5, "Team {$team->id} should appear in exactly 5 games");
    }
});

