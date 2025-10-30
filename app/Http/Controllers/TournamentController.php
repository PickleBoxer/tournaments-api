<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Http\Requests\StoreTournamentRequest;

class TournamentController extends Controller
{
    /**
     * Store a newly created tournament in storage.
     */
    public function __invoke(StoreTournamentRequest $request)
    {
        $tournament = Tournament::create($request->validated());

        return response()->json($tournament, 201);
    }
}
