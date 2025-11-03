<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Support\Collection;

class LeaderboardCalculatorService
{
    /**
     * Calculate tournament leaderboard with advanced tiebreaker rules
     *
     * @param Tournament $tournament
     * @return Collection
     */
    public function calculate(Tournament $tournament): Collection
    {
        // Eager load relationships to avoid N+1 queries
        $tournament->load(['teams', 'games' => function ($query) {
            $query->where('is_finalized', true);
        }]);

        $teams = $tournament->teams;
        $finalizedGames = $tournament->games;

        // Early return if no teams
        if ($teams->isEmpty()) {
            return collect();
        }

        // Index games by team for O(1) lookup instead of O(n) filtering
        $gamesIndex = $this->indexGamesByTeam($finalizedGames);

        // Calculate basic statistics for each team
        $standings = $this->buildTeamStatistics($teams, $gamesIndex);

        // Sort by points (descending)
        $standings = $standings->sortByDesc('points')->values();

        // Apply tiebreakers for teams with equal points
        $standings = $this->applyTiebreakers($standings, $finalizedGames);

        // Assign ranks
        return $standings->map(function ($stats, $index) {
            $stats['rank'] = $index + 1;
            return $stats;
        });
    }

    /**
     * Index games by team ID for fast lookup
     *
     * @param Collection $games
     * @return array
     */
    private function indexGamesByTeam(Collection $games): array
    {
        $index = [];

        foreach ($games as $game) {
            // Skip bye games (null team IDs)
            if ($game->home_team_id) {
                $index[$game->home_team_id][] = $game;
            }
            if ($game->away_team_id) {
                $index[$game->away_team_id][] = $game;
            }
        }

        return $index;
    }

    /**
     * Build statistics for all teams
     *
     * @param Collection $teams
     * @param array $gamesIndex
     * @return Collection
     */
    private function buildTeamStatistics(Collection $teams, array $gamesIndex): Collection
    {
        return $teams->map(function ($team) use ($gamesIndex) {
            return $this->calculateSingleTeamStats($team, $this->getTeamGamesFromIndex($team->id, $gamesIndex));
        });
    }

    /**
     * Calculate statistics for a single team
     *
     * @param $team
     * @param Collection $teamGames
     * @return array
     */
    private function calculateSingleTeamStats($team, Collection $teamGames): array
    {
        $stats = [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_difference' => 0,
            'points' => 0,
        ];

        foreach ($teamGames as $game) {
            // Skip bye games
            if (!$game->home_team_id || !$game->away_team_id) {
                continue;
            }

            $this->updateStatsFromGame($stats, $game, $team->id);
        }

        $stats['goal_difference'] = $stats['goals_for'] - $stats['goals_against'];

        return $stats;
    }

    /**
     * Update team stats based on a single game
     *
     * @param array $stats
     * @param $game
     * @param int $teamId
     * @return void
     */
    private function updateStatsFromGame(array &$stats, $game, int $teamId): void
    {
        $stats['played']++;

        $isHome = $game->home_team_id === $teamId;
        $teamGoals = $isHome ? $game->home_goals : $game->away_goals;
        $opponentGoals = $isHome ? $game->away_goals : $game->home_goals;

        $stats['goals_for'] += $teamGoals;
        $stats['goals_against'] += $opponentGoals;

        if ($teamGoals > $opponentGoals) {
            $stats['won']++;
            $stats['points'] += 3;
        } elseif ($teamGoals === $opponentGoals) {
            $stats['drawn']++;
            $stats['points'] += 1;
        } else {
            $stats['lost']++;
        }
    }

    /**
     * Get games for a specific team from the index
     *
     * @param int $teamId
     * @param array $gamesIndex
     * @return Collection
     */
    private function getTeamGamesFromIndex(int $teamId, array $gamesIndex): Collection
    {
        return collect($gamesIndex[$teamId] ?? []);
    }

    /**
     * Apply tiebreaker rules for teams with equal points
     *
     * @param Collection $standings
     * @param Collection $finalizedGames
     * @return Collection
     */
    private function applyTiebreakers(Collection $standings, Collection $finalizedGames): Collection
    {
        // Group teams by points
        $groupedByPoints = $standings->groupBy('points');

        $sortedStandings = collect();

        foreach ($groupedByPoints->sortKeysDesc() as $teamsWithSamePoints) {
            if ($teamsWithSamePoints->count() === 1) {
                // No tiebreaker needed
                $sortedStandings = $sortedStandings->concat($teamsWithSamePoints);
            } else {
                // Apply tiebreakers
                $sorted = $this->applyTiebreakerRules($teamsWithSamePoints, $finalizedGames);
                $sortedStandings = $sortedStandings->concat($sorted);
            }
        }

        return $sortedStandings->values();
    }

    /**
     * Apply specific tiebreaker rules in order
     *
     * @param Collection $teams
     * @param Collection $finalizedGames
     * @return Collection
     */
    private function applyTiebreakerRules(Collection $teams, Collection $finalizedGames): Collection
    {
        // Tiebreaker 1: Head-to-head results
        $teams = $this->applyHeadToHeadTiebreaker($teams, $finalizedGames);

        // Apply remaining tiebreakers to groups still tied
        return $this->applyRemainingTiebreakers($teams, $finalizedGames);
    }

    /**
     * Apply remaining tiebreakers recursively to tied groups
     *
     * @param Collection $teams
     * @param Collection $finalizedGames
     * @return Collection
     */
    private function applyRemainingTiebreakers(Collection $teams, Collection $finalizedGames): Collection
    {
        $result = collect();

        // Group by H2H results (fix: use json_encode for negative numbers)
        $grouped = $teams->groupBy(function ($team) {
            return json_encode([
                $team['h2h_points'] ?? 0,
                $team['h2h_goal_difference'] ?? 0,
                $team['h2h_goals_for'] ?? 0,
            ]);
        });

        foreach ($grouped as $group) {
            if ($group->count() === 1) {
                $result = $result->concat($group);
                continue;
            }

            // Apply sequential tiebreakers
            $result = $result->concat($this->applySequentialTiebreakers($group, $finalizedGames));
        }

        return $result;
    }

    /**
     * Apply goal difference, goals for, and average start time tiebreakers
     *
     * @param Collection $teams
     * @param Collection $finalizedGames
     * @return Collection
     */
    private function applySequentialTiebreakers(Collection $teams, Collection $finalizedGames): Collection
    {
        // Tiebreaker 2: Goal difference
        $sorted = $teams->sortByDesc('goal_difference')->values();
        $grouped = $sorted->groupBy('goal_difference');

        $result = collect();

        foreach ($grouped as $group) {
            if ($group->count() === 1) {
                $result = $result->concat($group);
                continue;
            }

            // Tiebreaker 3: Goals for
            $sorted = $group->sortByDesc('goals_for')->values();
            $subGrouped = $sorted->groupBy('goals_for');

            foreach ($subGrouped as $finalGroup) {
                if ($finalGroup->count() === 1) {
                    $result = $result->concat($finalGroup);
                    continue;
                }

                // Tiebreaker 4: Average start time
                $withAvgTime = $this->calculateAverageStartTime($finalGroup, $finalizedGames);
                $result = $result->concat($withAvgTime->sortBy('avg_start_time')->values());
            }
        }

        return $result;
    }

    /**
     * Calculate average start time for teams
     *
     * @param Collection $teams
     * @param Collection $finalizedGames
     * @return Collection
     */
    private function calculateAverageStartTime(Collection $teams, Collection $finalizedGames): Collection
    {
        return $teams->map(function ($teamStats) use ($finalizedGames) {
            $teamGames = $finalizedGames->filter(function ($game) use ($teamStats) {
                // Skip bye games
                if (!$game->home_team_id || !$game->away_team_id) {
                    return false;
                }

                return $game->home_team_id === $teamStats['team_id'] ||
                       $game->away_team_id === $teamStats['team_id'];
            });

            if ($teamGames->isEmpty()) {
                $teamStats['avg_start_time'] = null;
            } else {
                $avgTimestamp = $teamGames->avg(function ($game) {
                    return $game->starts_at->timestamp;
                });
                $teamStats['avg_start_time'] = $avgTimestamp;
            }

            return $teamStats;
        });
    }

    /**
     * Apply head-to-head tiebreaker
     *
     * @param Collection $teams
     * @param Collection $finalizedGames
     * @return Collection
     */
    private function applyHeadToHeadTiebreaker(Collection $teams, Collection $finalizedGames): Collection
    {
        $teamIds = $teams->pluck('team_id');

        // Extract games between tied teams only
        $h2hGames = $finalizedGames->filter(function ($game) use ($teamIds) {
            return $teamIds->contains($game->home_team_id) && $teamIds->contains($game->away_team_id);
        });

        if ($h2hGames->isEmpty()) {
            return $teams;
        }

        // Calculate mini-leaderboard
        $h2hStandings = $teams->map(function ($teamStats) use ($h2hGames) {
            $h2hPoints = 0;
            $h2hGoalDifference = 0;
            $h2hGoalsFor = 0;

            $teamH2HGames = $h2hGames->filter(function ($game) use ($teamStats) {
                return $game->home_team_id === $teamStats['team_id'] ||
                       $game->away_team_id === $teamStats['team_id'];
            });

            foreach ($teamH2HGames as $game) {
                $isHome = $game->home_team_id === $teamStats['team_id'];
                $teamGoals = $isHome ? $game->home_goals : $game->away_goals;
                $opponentGoals = $isHome ? $game->away_goals : $game->home_goals;

                $h2hGoalsFor += $teamGoals;
                $h2hGoalDifference += ($teamGoals - $opponentGoals);

                if ($teamGoals > $opponentGoals) {
                    $h2hPoints += 3;
                } elseif ($teamGoals === $opponentGoals) {
                    $h2hPoints += 1;
                }
            }

            $teamStats['h2h_points'] = $h2hPoints;
            $teamStats['h2h_goal_difference'] = $h2hGoalDifference;
            $teamStats['h2h_goals_for'] = $h2hGoalsFor;

            return $teamStats;
        });

        // Sort by H2H points, then H2H GD, then H2H GF
        return $h2hStandings->sortBy([
            ['h2h_points', 'desc'],
            ['h2h_goal_difference', 'desc'],
            ['h2h_goals_for', 'desc'],
        ])->values();
    }
}
