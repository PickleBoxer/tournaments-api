# Edge Cases Documentation

## Overview
This document describes all identified edge cases, their impact, likelihood, and test coverage for the Smart Naris tournament management system.

## Edge Case Priority Matrix

| Priority | Impact | Likelihood | Detectability | Risk Score |
|----------|--------|------------|---------------|------------|
| Critical | High   | Medium     | Hard          | 9-10       |
| High     | High   | Low        | Medium        | 7-8        |
| Medium   | Medium | Medium     | Easy          | 4-6        |
| Low      | Low    | Low        | Easy          | 1-3        |

---

## Critical Priority Edge Cases

### 1. Team Plays Itself (Self-Game)
**Description:**  
Schedule generator creates a game where `home_team_id = away_team_id`.

**Why Important:**  
Logically impossible scenario that breaks tournament integrity. Would corrupt leaderboard calculations and produce invalid results.

**Impact:** Critical (10/10)  
**Likelihood:** Low (2/10)  
**Detectability:** Hard without explicit check (3/10)  
**Risk Score:** 10

**Expected Behavior:**  
Schedule generator must NEVER create games where home team equals away team. Validation should prevent this at algorithm level.

**Test Coverage:**
- Property test: Assert all games have `home_team_id ≠ away_team_id`
- Unit test: `ScheduleGeneratorServiceTest::test_never_creates_self_games()`

---

### 2. Team Plays on Multiple Courts Simultaneously
**Description:**  
A team is scheduled to play on Court 1 and Court 2 at overlapping times.

**Why Important:**  
Physically impossible. Team cannot be in two places at once. Breaks tournament execution.

**Impact:** Critical (10/10)  
**Likelihood:** Medium (5/10) - could happen with naive parallel scheduling  
**Detectability:** Hard without time-slot validation (4/10)  
**Risk Score:** 10

**Expected Behavior:**  
For any team, their scheduled games must not overlap in time. Check: for all games involving Team X, `ends_at` of one game ≤ `starts_at` of next game.

**Test Coverage:**
- Property test: Assert no time overlaps per team
- Unit test: `ScheduleGeneratorServiceTest::test_no_concurrent_games_per_team()`
- Feature test: `ScheduleGenerationTest::test_validates_no_time_conflicts()`

---

### 3. Negative Goals Submitted
**Description:**  
API receives `home_goals = -1` or `away_goals = -5`.

**Why Important:**  
Negative goals are logically invalid. Would corrupt leaderboard statistics (GF, GA, GD) and point calculations.

**Impact:** Critical (9/10)  
**Likelihood:** Low (2/10) - requires malicious input or bug  
**Detectability:** Easy with validation (9/10)  
**Risk Score:** 9

**Expected Behavior:**  
Validation must reject any result where `home_goals < 0` or `away_goals < 0`. Return 422 with validation error.

**Test Coverage:**
- Feature test: `ResultManagementTest::test_rejects_negative_goals()`
- Property test: Assert all finalized games have `goals ≥ 0`

---

## High Priority Edge Cases

### 4. Schedule Regeneration with Finalized Games
**Description:**  
User attempts `POST /tournaments/{id}/schedule/generate` when one or more games already have finalized results.

**Why Important:**  
Would delete finalized results, causing data loss. Violates business rule that finalized games are immutable.

**Impact:** High (8/10)  
**Likelihood:** Medium (4/10)  
**Detectability:** Medium (6/10)  
**Risk Score:** 8

**Expected Behavior:**  
Endpoint must check if `ANY` game in tournament has `is_finalized = true`. If yes, return 422 error: "Cannot regenerate schedule: tournament has finalized games."

**Test Coverage:**
- Feature test: `ScheduleGenerationTest::test_prevents_regeneration_with_finalized_games()`

---

### 5. Delete Team with Finalized Results
**Description:**  
User attempts `DELETE /tournaments/{id}/teams/{teamId}` when team has participated in finalized games.

**Why Important:**  
Would orphan game results and corrupt leaderboard. Historical data would be lost.

**Impact:** High (8/10)  
**Likelihood:** Low (3/10)  
**Detectability:** Medium (5/10)  
**Risk Score:** 7

**Expected Behavior:**  
Endpoint must check if team has ANY finalized games (as home or away team). If yes, return 422 error: "Cannot delete team: has finalized games."

**Test Coverage:**
- Feature test: `TeamManagementTest::test_prevents_deletion_with_finalized_games()`

---

### 6. Unfinalize Game Twice
**Description:**  
User calls `POST /games/{id}/unfinalize` twice on same game.

**Why Important:**  
Business rule allows only ONE unfinalize per game. Second attempt should be blocked.

**Impact:** High (7/10)  
**Likelihood:** Medium (4/10)  
**Detectability:** Easy with counter (8/10)  
**Risk Score:** 7

**Expected Behavior:**  
First unfinalize: succeeds, increments `unfinalize_count` to 1.  
Second unfinalize: returns 422 error: "Game has already been unfinalized once."

**Test Coverage:**
- Feature test: `ResultManagementTest::test_unfinalize_only_once()`

---

### 7. Duplicate Team Name in Tournament
**Description:**  
User attempts to add team "Levi" when tournament already has team named "Levi".

**Why Important:**  
Business rule requires unique team names per tournament for identification.

**Impact:** High (7/10)  
**Likelihood:** Medium (5/10)  
**Detectability:** Easy with unique constraint (9/10)  
**Risk Score:** 7

**Expected Behavior:**  
Validation must reject with 422 error: "Team name already exists in this tournament."  
Database unique constraint: `UNIQUE(tournament_id, name)`

**Test Coverage:**
- Feature test: `TeamManagementTest::test_rejects_duplicate_team_name()`

---

## Medium Priority Edge Cases

### 8. Schedule Generation with Zero Teams
**Description:**  
User attempts to generate schedule for tournament with no teams added.

**Why Important:**  
Cannot create games without teams. Should fail gracefully with clear message.

**Impact:** Medium (5/10)  
**Likelihood:** Low (3/10)  
**Detectability:** Easy (8/10)  
**Risk Score:** 5

**Expected Behavior:**  
Return 422 error: "Cannot generate schedule: no teams in tournament."

**Test Coverage:**
- Feature test: `ScheduleGenerationTest::test_rejects_zero_teams()`

---

### 9. Schedule Generation with One Team
**Description:**  
Tournament has only 1 team, user generates schedule.

**Why Important:**  
Cannot play games alone. Need at least 2 teams for round-robin.

**Impact:** Medium (5/10)  
**Likelihood:** Low (2/10)  
**Detectability:** Easy (8/10)  
**Risk Score:** 5

**Expected Behavior:**  
Return 422 error: "Cannot generate schedule: need at least 2 teams."  
OR: Create empty schedule (0 games) with 200 success.

**Test Coverage:**
- Feature test: `ScheduleGenerationTest::test_handles_single_team()`

---

### 10. Odd Number of Teams (Bye Round)
**Description:**  
Tournament has 5 teams. Round-robin requires bye rounds.

**Why Important:**  
Must handle byes correctly: team doesn't play, no match created, no stats affected.

**Impact:** Medium (6/10)  
**Likelihood:** High (8/10)  
**Detectability:** Easy (7/10)  
**Risk Score:** 6

**Expected Behavior:**  
- 5 teams → 5 rounds, each team has 1 bye
- Bye team has no game in that round
- Leaderboard shows correct P (played) count excluding byes
- Total games = (n × (n-1)) / 2 = 10 games for 5 teams

**Test Coverage:**
- Feature test: `ScheduleGenerationTest::test_handles_odd_number_teams_with_byes()`

---

### 11. Match Duration Zero or Negative
**Description:**  
User creates tournament with `match_duration_minutes = 0` or `-10`.

**Why Important:**  
Invalid input. Games need positive duration for scheduling.

**Impact:** Medium (5/10)  
**Likelihood:** Low (2/10)  
**Detectability:** Easy (9/10)  
**Risk Score:** 5

**Expected Behavior:**  
Validation rejects with 422 error: "Match duration must be at least 1 minute."  
Validation rule: `match_duration_minutes|integer|min:1`

**Test Coverage:**
- Feature test: `TournamentManagementTest::test_rejects_invalid_match_duration()`

---

### 12. Invalid ISO8601 Datetime Format
**Description:**  
User submits `start_datetime = "2025-13-45"` or `"not a date"`.

**Why Important:**  
Cannot schedule games without valid start time.

**Impact:** Medium (5/10)  
**Likelihood:** Low (3/10)  
**Detectability:** Easy (9/10)  
**Risk Score:** 5

**Expected Behavior:**  
Validation rejects with 422 error: "Start datetime must be valid ISO8601 format."  
Validation rule: `start_datetime|required|date_format:Y-m-d\TH:i:s`

**Test Coverage:**
- Feature test: `TournamentManagementTest::test_rejects_invalid_datetime_format()`

---

## Low Priority Edge Cases

### 13. Court Number Out of Range
**Description:**  
User creates tournament with `num_courts = 0` or `num_courts = 10`.

**Why Important:**  
Business rule: 1-4 courts only. Out of range values should be rejected.

**Impact:** Low (3/10)  
**Likelihood:** Low (2/10)  
**Detectability:** Easy (9/10)  
**Risk Score:** 3

**Expected Behavior:**  
Validation rejects with 422 error: "Number of courts must be between 1 and 4."  
Validation rule: `num_courts|integer|between:1,4`

**Test Coverage:**
- Feature test: `TournamentManagementTest::test_validates_court_range()`

---

### 14. Large Number of Teams (Performance)
**Description:**  
Tournament with 20+ teams (190+ games for 20 teams).

**Why Important:**  
Schedule generation may be slow. Need to ensure performance acceptable.

**Impact:** Low (4/10)  
**Likelihood:** Low (3/10)  
**Detectability:** Easy (8/10)  
**Risk Score:** 4

**Expected Behavior:**  
- Schedule generation completes within reasonable time (<5 seconds)
- Leaderboard calculation remains fast (<2 seconds)
- No memory issues

**Test Coverage:**
- Performance test: `ScheduleGenerationTest::test_handles_large_team_count()`

---

## Test Coverage Summary

| Edge Case | Unit Test | Feature Test | Property Test |
|-----------|-----------|--------------|---------------|
| 1. Self-game | ✅ | ✅ | ✅ |
| 2. Concurrent games | ✅ | ✅ | ✅ |
| 3. Negative goals | ✅ | ✅ | ✅ |
| 4. Regenerate finalized | - | ✅ | - |
| 5. Delete with results | - | ✅ | - |
| 6. Unfinalize twice | - | ✅ | - |
| 7. Duplicate team name | - | ✅ | - |
| 8. Zero teams | - | ✅ | - |
| 9. Single team | - | ✅ | - |
| 10. Odd teams (bye) | ✅ | ✅ | - |
| 11. Invalid duration | - | ✅ | - |
| 12. Invalid datetime | - | ✅ | - |
| 13. Court range | - | ✅ | - |
| 14. Large teams | - | ✅ | - |

**Total Edge Cases:** 14  
**Test Coverage:** 100%  
**Property Tests (Invariants):** 3 (cases 1, 2, 3)
