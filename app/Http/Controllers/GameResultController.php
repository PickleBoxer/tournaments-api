<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Http\Requests\StoreGameResultRequest;
use Illuminate\Validation\ValidationException;

class GameResultController extends Controller
{
    /**
     * Store a newly created game result in storage.
     */
    public function store(Game $game, StoreGameResultRequest $request)
    {
        if ($game->is_finalized) {
            throw ValidationException::withMessages([
                'game' => 'Game is already finalized.',
            ]);
        }

        $game->update([
            'home_goals' => $request->validated()['home_goals'],
            'away_goals' => $request->validated()['away_goals'],
            'is_finalized' => true,
        ]);

        return response()->json($game, 200);
    }

    /**
     * Unfinalize the specified game.
     */
    public function update(Game $game)
    {
        if (!$game->is_finalized) {
            throw ValidationException::withMessages([
                'game' => 'Game is not finalized.',
            ]);
        }

        if ($game->unfinalize_count >= 1) {
            throw ValidationException::withMessages([
                'game' => 'Game has already been unfinalized once.',
            ]);
        }

        $game->update([
            'home_goals' => null,
            'away_goals' => null,
            'is_finalized' => false,
            'unfinalize_count' => $game->unfinalize_count + 1,
        ]);

        return response()->json($game, 200);
    }
}
