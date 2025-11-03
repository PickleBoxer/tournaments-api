<?php

use App\Models\Game;
use App\Models\Tournament;

test('prevents deletion with finalized games', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Alpha']);
    $team2 = $tournament->teams()->create(['name' => 'Beta']);

    // Create a finalized game where team1 is the home team
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

    $response = $this->deleteJson("/api/tournaments/{$tournament->id}/teams/{$team1->id}");

    $response->assertStatus(422)
        ->assertJsonValidationErrors('team')
        ->assertJsonPath('errors.team.0', 'Cannot delete team: has finalized games.');
});

test('rejects duplicate team name', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $tournament->teams()->create(['name' => 'Levi']);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/teams", [
        'name' => 'Levi',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('allows team deletion without finalized games', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $team1 = $tournament->teams()->create(['name' => 'Alpha']);
    $team2 = $tournament->teams()->create(['name' => 'Beta']);

    // Create a non-finalized game
    Game::create([
        'tournament_id' => $tournament->id,
        'home_team_id' => $team1->id,
        'away_team_id' => $team2->id,
        'court' => 1,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(30),
        'is_finalized' => false,
    ]);

    $response = $this->deleteJson("/api/tournaments/{$tournament->id}/teams/{$team1->id}");

    $response->assertStatus(204);
});

test('successfully creates team', function () {
    $tournament = Tournament::factory()->create([
        'num_courts' => 2,
        'match_duration_minutes' => 30,
        'start_datetime' => now()->addDay(),
    ]);

    $response = $this->postJson("/api/tournaments/{$tournament->id}/teams", [
        'name' => 'Dragons',
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment([
            'name' => 'Dragons',
        ]);

    $this->assertDatabaseHas('teams', [
        'tournament_id' => $tournament->id,
        'name' => 'Dragons',
    ]);
});
