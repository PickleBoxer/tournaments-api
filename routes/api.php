<?php

use App\Http\Controllers\GameResultController;
use App\Http\Controllers\GameScheduleController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::post('/tournaments', TournamentController::class)->name('tournaments.store');
Route::post('/tournaments/{tournament}/teams', [TeamController::class, 'store'])->name('tournaments.teams.store');
Route::delete('/tournaments/{tournament}/teams/{team}', [TeamController::class, 'destroy'])->name('tournaments.teams.destroy');

Route::post('/tournaments/{tournament}/schedule/generate', GameScheduleController::class)->name('tournaments.schedule.generate');

Route::post('/matches/{game}/result', [GameResultController::class, 'store'])->name('matches.result.store');
Route::post('/matches/{game}/unfinalize', [GameResultController::class, 'update'])->name('matches.unfinalize.update');

Route::get('/tournaments/{tournament}/leaderboard', [LeaderboardController::class, 'show'])->name('tournaments.leaderboard.show');
