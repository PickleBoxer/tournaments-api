<?php

use App\Models\Game;
use App\Models\Tournament;

test('prevents regeneration with finalized games', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Team 1']);
    $team2 = $tournament->teams()->create(['name' => 'Team 2']);

    // Create a finalized game
    Game::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'court' => 1,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
        'home_goals' => 2,
        'away_goals' => 1,
        'is_finalized' => true,
    ]);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/schedule/generate");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Cannot regenerate schedule: tournament has finalized games');
});

test('validates no time conflicts', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 1,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    // Create 4 teams
    for ($i = 1; $i <= 4; $i++) {
        $tournament->teams()->create(['name' => "Team {$i}"]);
    }

    $response = $this->postJson("/api/tournaments/{$tournament->id}/schedule/generate");

    $response->assertStatus(201);

    $games = Game::where('tournament_id', $tournament->id)->get();

    // Verify no team plays at overlapping times
    $teams = $tournament->teams;
    foreach ($teams as $team) {
        $teamGames = $games->filter(function ($game) use ($team) {
            return $game->home_team_id === $team->id || $game->away_team_id === $team->id;
        })->sortBy('starts_at')->values();

        for ($i = 0; $i < $teamGames->count() - 1; $i++) {
            $currentGame = $teamGames[$i];
            $nextGame = $teamGames[$i + 1];

            expect($currentGame->ends_at->lte($nextGame->starts_at))
                ->toBeTrue("Team {$team->id} has overlapping games");
        }
    }
});

test('rejects zero teams', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/schedule/generate");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Cannot generate schedule: tournament must have at least 2 teams');
});

test('handles single team', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $tournament->teams()->create(['name' => 'Team 1']);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/schedule/generate");

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Cannot generate schedule: tournament must have at least 2 teams');
});

test('handles odd number teams with byes', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    // Create 5 teams
    $teams = [];
    for ($i = 1; $i <= 5; $i++) {
        $teams[] = $tournament->teams()->create(['name' => "Team {$i}"]);
    }

    $response = $this->postJson("/api/tournaments/{$tournament->id}/schedule/generate");

    $response->assertStatus(201);

    $games = Game::where('tournament_id', $tournament->id)->get();

    // For 5 teams with byes: algorithm adds null team (6 total), creates all pairings
    // but skips when home is null. So we get 15 games total (some have null away_team_id)
    expect($games->count())->toBe(15);

    // Games with non-null away team (actual games, not byes)
    $nonByeGames = $games->filter(function ($game) {
        return $game->away_team_id !== null;
    });
    expect($nonByeGames->count())->toBe(10);

    // Games with null away team (bye games)
    $byeGames = $games->filter(function ($game) {
        return $game->away_team_id === null;
    });
    expect($byeGames->count())->toBe(5);

    // Each team should appear in exactly 5 games (4 real + 1 bye)
    foreach ($teams as $team) {
        $teamGames = $games->filter(function ($game) use ($team) {
            return $game->home_team_id === $team->id || $game->away_team_id === $team->id;
        });

        expect($teamGames->count())->toBe(5, "Team {$team->id} should appear in exactly 5 games");
    }

    // Verify response contains games with 'BYE' for bye games
    $responseData = $response->json();
    $byeGamesInResponse = collect($responseData)->filter(function ($game) {
        return $game['away_team'] === 'BYE';
    });
    expect($byeGamesInResponse->count())->toBe(5);
});

test('handles large team count', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 4,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    // Create 20 teams
    for ($i = 1; $i <= 20; $i++) {
        $tournament->teams()->create(['name' => "Team {$i}"]);
    }

    $startTime = microtime(true);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/schedule/generate");

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    $response->assertStatus(201);

    // Should complete within 5 seconds
    expect($executionTime)->toBeLessThan(5.0);

    $games = Game::where('tournament_id', $tournament->id)->get();

    // For 20 teams, total games should be (20 * 19) / 2 = 190
    expect($games->count())->toBe(190);
});

