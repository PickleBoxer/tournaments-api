<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\Team;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleGeneratorService
{
    /**
     * Generate complete round-robin game schedule with multi-court support
     *
     * @throws InvalidArgumentException
     */
    public function generate(Tournament $tournament): void
    {
        // Check if regeneration is allowed
        if (! $tournament->canRegenerateSchedule()) {
            throw new InvalidArgumentException(
                'Cannot regenerate schedule: tournament has finalized games'
            );
        }

        // Validate tournament has minimum requirements
        if ($tournament->num_courts < 1) {
            throw new InvalidArgumentException(
                'Tournament must have at least 1 court'
            );
        }

        DB::transaction(function () use ($tournament): void {
            // Delete existing non-finalized games
            $this->clearExistingSchedule($tournament);

            // Get all teams
            $teams = $tournament->teams()->get();

            if ($teams->count() < 2) {
                throw new InvalidArgumentException(
                    'Cannot generate schedule: tournament must have at least 2 teams'
                );
            }

            // Add bye if odd number of teams
            $teamsArray = $this->prepareTeamsArray($teams);

            // Generate all unique pairings using round-robin algorithm
            $allPairings = $this->generateRoundRobinPairings($teamsArray);

            // Assign games to courts and time slots
            $this->assignGamesToCourts($tournament, $allPairings);
        });
    }

    /**
     * Clear existing non-finalized games
     */
    private function clearExistingSchedule(Tournament $tournament): void
    {
        $tournament->games()
            ->where('is_finalized', false)
            ->delete();
    }

    /**
     * Prepare teams array and add bye if needed
     */
    private function prepareTeamsArray(Collection $teams): array
    {
        $teamsArray = $teams->pluck('id')->toArray();

        // Add null as "bye" placeholder if odd number
        if (count($teamsArray) % 2 !== 0) {
            $teamsArray[] = null;
        }

        return $teamsArray;
    }

    /**
     * Generate round-robin pairings using the circle/rotation method
     * Team 1 is fixed, others rotate clockwise
     * Each team plays every other team exactly once
     *
     * @return array Array of pairings [home_team_id, away_team_id] (away can be null for bye)
     */
    private function generateRoundRobinPairings(array $teams): array
    {
        $n = count($teams);
        $rounds = $n - 1;
        $matchesPerRound = $n / 2;
        $pairings = [];

        // Circle method: fix first team (index 0), rotate others clockwise
        for ($round = 0; $round < $rounds; $round++) {
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $home = ($round + $match) % ($n - 1);
                $away = ($n - 1 - $match + $round) % ($n - 1);

                // Last team (fixed position) plays in the first match of each round
                if ($match === 0) {
                    $away = $n - 1;
                }

                $homeTeamId = $teams[$home];
                $awayTeamId = $teams[$away];

                // Always create pairing, even if away team is bye (null)
                // Skip only if home team is bye
                if ($homeTeamId !== null) {
                    $pairings[] = [
                        'home_team_id' => $homeTeamId,
                        'away_team_id' => $awayTeamId, // Can be null (bye)
                    ];
                }
            }
        }

        return $pairings;
    }

    /**
     * Assign games to courts and time slots ensuring no team conflicts
     * Courts are numbered 1 through num_courts
     */
    private function assignGamesToCourts(Tournament $tournament, array $pairings): void
    {
        $numCourts = $tournament->num_courts;
        $startTime = $tournament->start_datetime;
        $matchDuration = $tournament->match_duration_minutes;

        $currentSlotIndex = 0;
        $gamesInCurrentSlot = [];
        $busyTeamsInSlot = [];

        foreach ($pairings as $pairing) {
            $homeTeamId = $pairing['home_team_id'];
            $awayTeamId = $pairing['away_team_id']; // Can be null for bye

            // Check if either team is already playing in current slot
            $hasConflict = isset($busyTeamsInSlot[$homeTeamId]) ||
                          ($awayTeamId !== null && isset($busyTeamsInSlot[$awayTeamId]));

            // Check if current slot is full
            $slotFull = count($gamesInCurrentSlot) >= $numCourts;

            // Move to next time slot if needed
            if ($hasConflict || $slotFull) {
                $currentSlotIndex++;
                $gamesInCurrentSlot = [];
                $busyTeamsInSlot = [];
            }

            // Assign court number (1-indexed: 1 through num_courts)
            $courtNumber = count($gamesInCurrentSlot) + 1;

            // Calculate game times
            $startsAt = $this->calculateGameStartTime(
                $startTime,
                $currentSlotIndex,
                $matchDuration
            );
            $endsAt = $startsAt->copy()->addMinutes($matchDuration);

            // Create game (away_team_id can be null for bye)
            Game::create([
                'tournament_id' => $tournament->id,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'court' => $courtNumber,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_finalized' => false,
                'unfinalize_count' => 0,
            ]);

            // Mark teams as busy in this slot
            $gamesInCurrentSlot[] = $pairing;
            $busyTeamsInSlot[$homeTeamId] = true;

            // Only mark away team as busy if not a bye
            if ($awayTeamId !== null) {
                $busyTeamsInSlot[$awayTeamId] = true;
            }
        }
    }

    /**
     * Calculate game start time based on slot index
     */
    private function calculateGameStartTime(
        Carbon $baseStartTime,
        int $slotIndex,
        int $matchDurationMinutes
    ): Carbon {
        return $baseStartTime->copy()->addMinutes($slotIndex * $matchDurationMinutes);
    }
}
