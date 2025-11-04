# Design Document - Smart Naris Tournament Management System

## Overview
This document explains the key architectural decisions, algorithms, and data flow for the tournament management backend API.

## Database Schema Design

### Core Tables
- **tournaments**: Stores tournament metadata (name, start time, match duration, court count)
- **teams**: Teams participating in tournaments (unique names per tournament)
- **games**: Game schedule with results, times, court assignments, and finalization tracking (used to be Matches but renamed becouse of php reserved word)

### Key Design Decisions
1. **No separate bye entity**: When teams are odd, we handle byes in scheduling logic, not as database records
2. **Finalization tracking**: `is_finalized` boolean + `unfinalize_count` integer on games table enables "unfinalize once" rule
3. **Nullable results**: `home_goals` and `away_goals` are nullable until game is played
4. **Indexed foreign keys**: All relationships indexed for query performance on leaderboard calculations

## Round-Robin Scheduling Algorithm

### Standard Rotation Method
For `n` teams, we need `n-1` rounds (or `n` rounds if odd number of teams for bye rotation).

**Algorithm Steps:**
1. **Team pairing**: Use circle method where one team is fixed, others rotate
   - Fixed team: Team 1
   - Rotating teams: Team 2 to Team n arranged in circle
   - Each round: pair adjacent teams, rotate circle
   
2. **Odd team handling**: If odd number of teams, add `null` placeholder. When a team pairs with `null`, they have a bye (no game created)

3. **Match generation**: For each round, create all possible pairs ensuring each team plays once per round

### Multi-Court Parallel Scheduling

**Time Slot Assignment:**
- Group games into rounds (a round = all games where each team plays at most once)
- Within each round, distribute games across available courts
- Calculate time slots: `start_time + (round_number * match_duration)`

**Conflict Prevention:**
- Before assigning game to time slot, verify neither home nor away team is already playing in that slot
- If conflict detected, push game to next available slot where both teams are free

**Example with 4 teams, 2 courts:**
```
Round 1: A-B (Court 1, 09:00-09:20), C-D (Court 2, 09:00-09:20)
Round 2: A-C (Court 1, 09:20-09:40), B-D (Court 2, 09:20-09:40)
Round 3: A-D (Court 1, 09:40-10:00), B-C (Court 2, 09:40-10:00)
```

## Leaderboard Calculation

### Basic Statistics
For each team, calculate from finalized games:
- **P** (Played): Total games with final results
- **W** (Wins): Games where team scored more goals
- **D** (Draws): Games with equal goals
- **L** (Losses): Games where opponent scored more
- **GF** (Goals For): Total goals scored by team
- **GA** (Goals Against): Total goals conceded
- **GD** (Goal Difference): GF - GA
- **Pts** (Points): (W × 3) + (D × 1)

### Tiebreaker Rules (Applied in Order)

#### 1. Head-to-Head (H2H)
When 2+ teams have equal points:
- Extract only games between tied teams (mini-table)
- Recalculate Pts, GD, GF within this subset
- Sort tied teams by these H2H metrics
- If still tied after H2H, proceed to next tiebreaker

#### 2. Goal Difference (Overall)
If H2H doesn't resolve, use overall GD (from all games)

#### 3. Goals For (Overall)
If still tied, team with more total goals scored ranks higher

#### 4. Average Game Start Time
Final tiebreaker: Average of ALL scheduled game start times (including unplayed games)
- Earlier average = higher rank
- Provides deterministic ordering even for unplayed games

## API Design Patterns

### RESTful Endpoints
- Resource-based URLs (`/tournaments`, `/teams`, `/games`)
- HTTP verbs: POST (create), GET (read), DELETE (remove)
- Nested resources: `/tournaments/{id}/teams`

### Response Format
Standard JSON responses following Laravel conventions:
- Success: `200 OK` with resource data
- Created: `201 Created` with resource data
- Validation Error: `422 Unprocessable Entity` with error messages
- Not Found: `404 Not Found`

### Validation Strategy
- Form Request classes for input validation
- Database constraints for data integrity (unique, foreign keys)
- Business rule validation in services (e.g., "can't regenerate if finalized")

## Business Logic Enforcement

### Schedule Regeneration Protection
```php
if (! $tournament->canRegenerateSchedule()) {
    throw new InvalidArgumentException(
        'Cannot regenerate schedule: tournament has finalized games'
    );
}
```

### Team Deletion Protection
```php
if ($team->hasAnyFinalizedGames()) {
    throw ValidationException::withMessages([
        'team' => 'Cannot delete team: has finalized games.',
    ]);
}
```

### Unfinalize Limit
```php
if ($game->unfinalize_count >= 1) {
    throw ValidationException::withMessages([
        'game' => 'Game has already been unfinalized once.',
    ]);
}
```

## Service Layer Architecture

### ScheduleGeneratorService
**Responsibilities:**
- Generate all team pairs (round-robin)
- Assign courts and time slots
- Validate no team conflicts
- Persist games to database

**Input:** Tournament object  
**Output:** void (creates games)

### LeaderboardCalculatorService
**Responsibilities:**
- Aggregate game statistics per team
- Apply point calculation
- Execute tiebreaker logic
- Return ranked team list

**Input:** Tournament object  
**Output:** Collection of team standings

## Testing Strategy

### Test Pyramid
1. **Unit Tests**: Services with isolated logic (schedule generation, leaderboard calculation)
2. **Feature Tests**: API endpoints with database transactions
3. **Property Tests**: Invariant verification (no self-games, no time conflicts)

### Edge Case Coverage
All 14+ edge cases documented in EDGECASES.md have corresponding tests, with top 5 critical cases having both unit and property tests.

