<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Models\Game;
use App\Models\Tournament;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    /**
     * Store a newly created game in storage.
     */
    public function store(StoreGameRequest $request, Tournament $tournament)
    {
        $data = $request->validated();
        $game = $tournament->games()->create($data);

        return response()->json($game, 201);
    }

    /**
     * Remove the specified game from storage.
     */
    public function destroy(Tournament $tournament, Game $game)
    {
        if ($game->is_finalized) {
            throw ValidationException::withMessages([
                'game' => 'Cannot delete game: already finalized.',
            ]);
        }
        $game->delete();

        return response()->json(null, 204);
    }
}
