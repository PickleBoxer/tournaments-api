<?php

test('rejects invalid match duration', function (): void {
    $response = $this->postJson('/api/tournaments', [
        'name' => 'Test Tournament',
        'start_datetime' => now()->addDay()->toIso8601String(),
        'match_duration_minutes' => 0,
        'num_courts' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('match_duration_minutes');
});

test('rejects negative match duration', function (): void {
    $response = $this->postJson('/api/tournaments', [
        'name' => 'Test Tournament',
        'start_datetime' => now()->addDay()->toIso8601String(),
        'match_duration_minutes' => -10,
        'num_courts' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('match_duration_minutes');
});

test('rejects invalid datetime format', function (): void {
    $response = $this->postJson('/api/tournaments', [
        'name' => 'Test Tournament',
        'start_datetime' => '2025-13-45',
        'match_duration_minutes' => 30,
        'num_courts' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('start_datetime');
});

test('rejects non datetime string', function (): void {
    $response = $this->postJson('/api/tournaments', [
        'name' => 'Test Tournament',
        'start_datetime' => 'not a date',
        'match_duration_minutes' => 30,
        'num_courts' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('start_datetime');
});

test('validates court range - zero courts', function (): void {
    $response = $this->postJson('/api/tournaments', [
        'name' => 'Test Tournament',
        'start_datetime' => now()->addDay()->toIso8601String(),
        'match_duration_minutes' => 30,
        'num_courts' => 0,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('num_courts');
});

test('validates court range - too many courts', function (): void {
    $response = $this->postJson('/api/tournaments', [
        'name' => 'Test Tournament',
        'start_datetime' => now()->addDay()->toIso8601String(),
        'match_duration_minutes' => 30,
        'num_courts' => 10,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('num_courts');
});

test('successfully creates tournament with valid data', function (): void {
    $startDateTime = now()->addDay()->toIso8601String();

    $response = $this->postJson('/api/tournaments', [
        'name' => 'Summer Championship',
        'start_datetime' => $startDateTime,
        'match_duration_minutes' => 30,
        'num_courts' => 2,
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment([
            'name' => 'Summer Championship',
            'match_duration_minutes' => 30,
            'num_courts' => 2,
        ]);

    $this->assertDatabaseHas('tournaments', [
        'name' => 'Summer Championship',
        'match_duration_minutes' => 30,
        'num_courts' => 2,
    ]);
});

test('accepts valid court range', function (): void {
    for ($courts = 1; $courts <= 4; $courts++) {
        $response = $this->postJson('/api/tournaments', [
            'name' => "Tournament {$courts}",
            'start_datetime' => now()->addDay()->toIso8601String(),
            'match_duration_minutes' => 30,
            'num_courts' => $courts,
        ]);

        $response->assertStatus(201);
    }
});
