# Smart Naris - Tournament Management API

A Laravel-based backend system for managing football tournaments with round-robin scheduling, multiple courts, and comprehensive leaderboard tracking.

## Features

- 🏆 **Tournament Management** - Create and configure tournaments with custom settings
- 👥 **Team Management** - Add/remove teams with unique name validation
- 📅 **Automatic Scheduling** - Round-robin schedule generation with multi-court support
- ⚽ **Results Tracking** - Record match results with finalization and unfinalize protection
- 📊 **Dynamic Leaderboard** - Real-time standings with advanced tiebreaker rules
- ✅ **Comprehensive Testing** - Unit, feature, and property tests with edge case coverage

## Requirements

- PHP 8.3+
- Composer
- Laravel 12

## Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd tournaments-api
```

### 2. Setup 
```bash
composer run setup
```

### 3. Run Migrations and Seeders
```bash
php artisan migrate:fresh --seed
```

This will:
- Create all required database tables
- Seed the L1 test dataset (4 teams: A, B, C, D with expected results)

### 6. Start Development Server
```bash
composer run dev
```

This command starts both:
- Laravel API server (`http://127.0.0.1:8000`)
- Queue listener for background jobs

## API Documentation

### Interactive Documentation
Visit the auto-generated API documentation at:
```
http://127.0.0.1:8000/docs/api
```

This provides:
- Interactive API explorer (Try It feature enabled)
- Complete endpoint documentation with request/response examples
- Automatically generated from your code (powered by Scramble)
- OpenAPI 3.1 specification available at `/docs/api.json`

### Base URL
```
http://127.0.0.1:8000/api
```

## Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Run With Coverage
```bash
php artisan test --coverage
```

### Test Categories

1. **Feature Tests** - API endpoint integration tests
   - Tournament CRUD operations
   - Team management with validation
   - Schedule generation with edge cases
   - Result management and finalization

2. **Unit Tests** - Service layer logic tests
   - Schedule generator algorithm
   - Leaderboard calculator with tiebreakers
   - Edge case validations

3. **Property Tests** - Invariant verification
   - No self-matches (team vs itself)
   - No concurrent matches per team
   - All results have non-negative goals

## Documentation

- **[DESIGN.md](DESIGN.md)** - Architecture decisions, algorithms, and data flow
- **[EDGECASES.md](EDGECASES.md)** - Comprehensive edge case documentation with test coverage
- **[COLLABORATION.md](COLLABORATION.md)** - Tool usage and development process

## Edge Cases Coverage

The system handles 14+ documented edge cases including:

See [EDGECASES.md](EDGECASES.md) for complete details.

## Edge Case Test Coverage Mapping

| Edge Case | Unit Test | Feature Test | Property Test |
|-----------|-----------|--------------|---------------|
| Self-game | ✅ | ✅ | ✅ |
| Concurrent games | ✅ | ✅ | ✅ |
| Negative goals | ✅ | ✅ | ✅ |
| Regenerate finalized | - | ✅ | - |
| Delete with results | - | ✅ | - |
| Unfinalize twice | - | ✅ | - |
| Duplicate team name | - | ✅ | - |
| Zero teams | - | ✅ | - |
| Single team | - | ✅ | - |
| Odd teams (bye) | ✅ | ✅ | - |
| Invalid duration | - | ✅ | - |
| Invalid datetime | - | ✅ | - |
| Court range | - | ✅ | - |
| Large teams | - | ✅ | - |

## Development Workflow

### Start Development Environment
```bash
composer run dev
```

### Run Database Migrations
```bash
php artisan migrate
```

### Fresh Database with Seed Data
```bash
php artisan migrate:fresh --seed
```

### Clear Caches
```bash
php artisan optimize:clear
```

## Troubleshooting

### Migration Errors
```bash
php artisan migrate:fresh
```

### Test Failures
```bash
php artisan config:clear
php artisan test
```
