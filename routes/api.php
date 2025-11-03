<?php

use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


use App\Http\Controllers\GameController;
use App\Http\Controllers\GameResultController;
use App\Http\Controllers\GameScheduleController;

Route::post('/tournaments', TournamentController::class);
Route::post('/tournaments/{tournament}/teams', [TeamController::class, 'store']);
Route::delete('/tournaments/{tournament}/teams/{team}', [TeamController::class, 'destroy']);

Route::post('/tournaments/{tournament}/games', [GameController::class, 'store']);
Route::delete('/tournaments/{tournament}/games/{game}', [GameController::class, 'destroy']);

Route::post('/tournaments/{tournament}/schedule/generate', GameScheduleController::class);

Route::post('/games/{game}/result', [GameResultController::class, 'store']);
Route::post('/games/{game}/unfinalize', [GameResultController::class, 'update']);

Route::get('/tournaments/{tournament}/leaderboard', [LeaderboardController::class, 'show']);
