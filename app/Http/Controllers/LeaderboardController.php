<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\LeaderboardCalculatorService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function __construct(
        private LeaderboardCalculatorService $leaderboardCalculator
    ) {
    }

    /**
     * Get tournament leaderboard
     *
     * @param Tournament $tournament
     * @return JsonResponse
     */
    public function show(Tournament $tournament): JsonResponse
    {
        $leaderboard = $this->leaderboardCalculator->calculate($tournament);

        return response()->json([
            'tournament_id' => $tournament->id,
            'tournament_name' => $tournament->name,
            'leaderboard' => $leaderboard,
        ]);
    }
}
