<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\Tournament;
use App\Services\ScheduleGeneratorService;
use Illuminate\Http\JsonResponse;

class GameScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleGeneratorService $scheduleGenerator
    ) {}

    /**
     * Generate round-robin schedule for the tournament
     */
    public function __invoke(Tournament $tournament): JsonResponse
    {
        try {
            $this->scheduleGenerator->generate($tournament);

            $games = $tournament->games()
                ->with(['homeTeam', 'awayTeam'])
                ->get();

            return response()->json(GameResource::collection($games), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception) {
            return response()->json([
                'error' => 'Failed to generate schedule',
            ], 500);
        }
    }
}
