# Test Scenario Points Integration

## Overview

This feature integrates test scenario verification with the points system, awarding or deducting points based on test scenario verification results for **Weekly Tasks only**.

## Business Rules

### When Points Are Awarded/Deducted

1. **Task Type**: Only **WEEKLY** tasks award points for test scenarios
    - MAIN tasks: No points
    - SUBTASK tasks: No points
    - WEEKLY tasks: Points awarded/deducted

2. **Trigger Event**: Points are awarded/deducted only when test scenarios are **verified** (not just completed)
    - Must have `verified_at` timestamp set
    - Must have `verified_by` user set
    - Status change alone is not enough

3. **Assignee Required**: Task must have an assignee (`assigned_to` field)
    - Points go to the task assignee (developer who worked on it)
    - No assignee = no points

### Point Values

- **Test Scenario Verified (Passed)**: +1 point
- **Test Scenario Verified (Failed)**: -1 point

## Technical Implementation

### New Point Transaction Types

```php
// Added to PointTransactionType enum
case TEST_SCENARIO_VERIFIED = 'test_scenario_verified';    // +1 point
case TEST_SCENARIO_FAILED = 'test_scenario_failed';        // -1 point
```

### Observer Pattern

- `TaskTestScenarioObserver` monitors `TaskTestScenario` model changes
- Triggers on `updating` event when `verified_at` field changes
- Uses `UnifiedPointService` to maintain consistency with existing point system

### Database Integration

Points are recorded in both systems for consistency:

- `point_transactions` table (primary system)
- `user_points` table (legacy compatibility)
- `users.total_points` field (cached total)

## Usage Examples

### Scenario 1: Weekly Task Test Scenario Passes

```php
// Developer completes test scenario
$scenario->markAsCompleted($developer);

// Reviewer verifies as passed
$scenario->verify($reviewer, true, 'All tests pass');
// Result: +1 point awarded to task assignee
```

### Scenario 2: Weekly Task Test Scenario Fails

```php
// Reviewer verifies as failed
$scenario->verify($reviewer, false, 'Login test failed');
// Result: -1 point deducted from task assignee
```

### Scenario 3: Main Task Test Scenario (No Points)

```php
// Even if verified as passed, no points awarded for MAIN tasks
$mainTaskScenario->verify($reviewer, true);
// Result: 0 points (MAIN tasks don't award test scenario points)
```

## API Integration

The feature works automatically through the existing test scenario verification endpoints:

```http
POST /api/v1/test-scenarios/{scenario}/verify
{
    "passed": true,
    "notes": "All tests pass successfully"
}
```

When this endpoint is called for a Weekly task test scenario, points are automatically awarded/deducted.

## Testing

Comprehensive integration tests are available in:

- `tests/Feature/TaskTestScenarioPointsTest.php`

Tests cover:

- ✅ Points awarded for passed Weekly task scenarios
- ✅ Points deducted for failed Weekly task scenarios
- ✅ No points for MAIN task scenarios
- ✅ No points for SUBTASK scenarios
- ✅ No points when task has no assignee
- ✅ No points when verification timestamp not set

## Monitoring

Points can be monitored through:

- User points history API
- Points leaderboard
- Admin points statistics
- Point transaction reports

All test scenario points appear with appropriate transaction types and source references for full traceability.
