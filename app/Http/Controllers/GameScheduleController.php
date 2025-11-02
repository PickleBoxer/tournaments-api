<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\ScheduleGeneratorService;
use Illuminate\Http\JsonResponse;

class GameScheduleController extends Controller
{
    public function __construct(
        private ScheduleGeneratorService $scheduleGenerator
    ) {}

    /**
     * Generate round-robin schedule for the tournament
     */
    public function generate(Tournament $tournament): JsonResponse
    {
        try {
            $this->scheduleGenerator->generate($tournament);

            $games = $tournament->games()
                ->with(['homeTeam', 'awayTeam'])
                ->get()
                ->map(function ($game) {
                    return [
                        'match_id' => $game->id,
                        'home_team' => $game->homeTeam->name,
                        'away_team' => $game->awayTeam->name,
                        'court' => $game->court,
                        'starts_at' => $game->starts_at->toISOString(),
                        'ends_at' => $game->ends_at->toISOString(),
                    ];
                });

            return response()->json($games, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate schedule',
            ], 500);
        }
    }
}
