<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use App\Http\Requests\StoreTeamRequest;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    /**
     * Store a newly created team in storage.
     */
    public function store(StoreTeamRequest $request, Tournament $tournament)
    {
        $team = $tournament->teams()->create($request->validated());

        return response()->json($team, 201);
    }

    /**
     * Remove the specified team from storage.
     */
    public function destroy(Tournament $tournament, Team $team)
    {
        if ($team->hasAnyFinalizedGames()) {
            throw ValidationException::withMessages([
                'team' => 'Cannot delete team: has finalized games.',
            ]);
        }

        $team->delete();

        return response()->json(null, 204);
    }
}
