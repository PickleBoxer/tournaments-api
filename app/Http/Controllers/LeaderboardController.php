<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\LeaderboardCalculatorService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardCalculatorService $leaderboardCalculator
    ) {}

    /**
     * Get tournament leaderboard
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
