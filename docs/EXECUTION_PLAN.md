# Phase 0 incremental implementation

| PR | Deliverable | Gate |
|---|---|---|
| P0-01 | Brand-new Laravel 13 API foundation + React Vite UI shell; Docker and CI; ADRs | API health, typecheck, tests, smoke build |
| P0-02 | Sanctum first-party SPA login, CSRF, MFA, company membership, RBAC/policies | Deny other-company access, MFA/security integration tests |
| P0-03 | Verified PostgreSQL schema constraints, RLS pilot, role grants | Same-company composite FK, transaction/pooling leakage tests |
| P0-04 | Financial posting service, periods, accounts, reversals | No unbalanced posting, closed period writes or in-place edits |
| P0-05 | Audit, transactional outbox, idempotent job pipeline | Retries cannot duplicate invoice/posting/approval |
| P0-06 | Shared UI design system, typed API generator, navigation | Accessible RTL/mobile UI, route authorization |
| P0-07 | Staging compose/deploy, backup/restore exercise, E2E | Isolation, recovery and smoke tests |

## First vertical slice after P0
Lead → proposal (versioned) → approved contract → project/milestone → PM approval → draft invoice → Finance issue → manual partial payment → double-entry posting. Include Egypt/Italy company segregation and intercompany invoicing tests.

## Current implementation status
P0-01 scaffold created; backend test execution/actual database migration must be verified in a Composer/CI environment. No migrations or deploy against production.

