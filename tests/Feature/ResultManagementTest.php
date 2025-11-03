<?php

use App\Models\Game;
use App\Models\Tournament;

test('rejects negative goals', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Team 1']);
    $team2 = $tournament->teams()->create(['name' => 'Team 2']);

    $game = Game::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'court' => 1,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
    ]);

    $response = $this->postJson("/api/games/{$game->id}/result", [
        'home_goals' => -1,
        'away_goals' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('home_goals');
});

test('rejects negative away goals', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Team 1']);
    $team2 = $tournament->teams()->create(['name' => 'Team 2']);

    $game = Game::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'court' => 1,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
    ]);

    $response = $this->postJson("/api/games/{$game->id}/result", [
        'home_goals' => 3,
        'away_goals' => -5,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('away_goals');
});

test('unfinalize only once', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Team 1']);
    $team2 = $tournament->teams()->create(['name' => 'Team 2']);

    $game = Game::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'court' => 1,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
        'home_goals' => 2,
        'away_goals' => 1,
        'is_finalized' => true,
        'unfinalize_count' => 0,
    ]);

    // First unfinalize - should succeed
    $response = $this->postJson("/api/games/{$game->id}/unfinalize");
    $response->assertStatus(200);

    $game->refresh();
    expect($game->unfinalize_count)->toBe(1);
    expect($game->is_finalized)->toBeFalse();

    // Finalize again
    $game->update([
        'is_finalized' => true,
        'home_goals' => 3,
        'away_goals' => 2,
    ]);

    // Second unfinalize - should fail
    $response = $this->postJson("/api/games/{$game->id}/unfinalize");
    $response->assertStatus(422)
        ->assertJsonValidationErrors('game')
        ->assertJsonPath('errors.game.0', 'Game has already been unfinalized once.');
});

test('stores valid result and finalizes game', function (): void {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Team 1']);
    $team2 = $tournament->teams()->create(['name' => 'Team 2']);

    $game = Game::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'court' => 1,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
    ]);

    $response = $this->postJson("/api/games/{$game->id}/result", [
        'home_goals' => 3,
        'away_goals' => 2,
    ]);

    $response->assertStatus(200);

    $game->refresh();
    expect($game->home_goals)->toBe(3);
    expect($game->away_goals)->toBe(2);
    expect($game->is_finalized)->toBeTrue();
});
