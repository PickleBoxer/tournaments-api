<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;

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
