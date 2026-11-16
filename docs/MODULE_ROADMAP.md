# Module Roadmap — End-to-End ERP V2

Status key: `NOT STARTED` = design only; `SCAFFOLD` = source code exists but not verified; `IN PROGRESS` = active task with evidence; `VERIFIED` = acceptance tests passed and PR reviewed; `BLOCKED` = dependency/external signoff prevents delivery.
All statuses are as of **2026-11-16** and must be changed only with linked evidence. Full business requirements: [BUSINESS_RULES.md](BUSINESS_RULES.md).

| ID | Module / capability | Prerequisite(s) | Initial delivery gate | Status |
|---|---|---|---|---|
| F00 | Platform, CI, Docker, PostgreSQL, Redis | None | Real CI/build/migration smoke tests | VERIFIED (automated CI/build/migration checks; no production release) |
| F01 | Company model, permissions, internal auth/MFA | F00 | Company scoping and unauthorized-access negatives | IN PROGRESS ([Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4), backend draft PR #6 and frontend draft PR #2; security remediations implemented; backend 24 tests PASS, pinned Chromium first-enrollment E2E PASS, independent review and formal security-policy approval pending) |
| F02 | General Ledger foundational controls | F00, F01 | Balanced immutable posting, locked periods, reversals | NOT STARTED (schema scaffold) |
| F03 | Audit, outbox, idempotency, notifications base | F00, F01 | Verified retry/deduplication and audit | NOT STARTED (tables scaffold) |
| M01 | CRM / leads / contacts / attribution | F01 | Sales/social/platform/acquired-by/manager activity history | NOT STARTED |
| M02 | Proposals / versions / approvals / quotations | M01, F01 | Approved snapshot with price, tax, milestones, installments | NOT STARTED |
| M03 | Contracts / change requests / cancellation clauses | M02 | Convert without retyping; signed immutability & amendments | NOT STARTED |
| M04 | Projects / phases / milestones / cross-company team | M03, F01 | PM approval authorizes due installment, not payment | NOT STARTED |
| M05 | Tasks / subtasks / reviews / dependencies / views | M04 | Role-safe Kanban/List and task approval workflow | NOT STARTED |
| M06 | Time tracking / timesheets / cost attribution | M05 | Timer/manual, PM approval, no overlapping timer billing | NOT STARTED |
| M07 | Attendance / flexible schedules / leave balances | F01, M05 | Smart check-in and staged leave approvals | NOT STARTED |
| M08 | Invoices / partial payments / bank ledgers / FX | F02, M03, M04 | Issue after Finance review for milestones; no duplicate posting | NOT STARTED |
| M09 | Full accounting / AP / tax / financial statements | F02, M08 | Company ledgers, reconciliation, external-accountant exports | NOT STARTED |
| M10 | HR profiles / payroll / advances / benefits | F01, M06, M07, M09 | HR→Finance→Management approval; payment separately recorded | NOT STARTED |
| M11 | Sales commissions / performance reviews | M01, M08, M10 | Collection-based default; recurring policy; manager/HR reviews | NOT STARTED |
| M12 | SaaS products / plans / customer instances | M03, M08 | Shared/dedicated deployments, custom pricing & historical terms | NOT STARTED |
| M13 | Subscriptions / auto-renewal / hosting billing | M08, M12, F03 | One invoice per cycle; cancellation penalty & service stop rules | NOT STARTED |
| M14 | Support / tickets / maintenance / SLA / hours rollover | M05, M13 | Contract-specific hours, priority, ticket-task linkage | NOT STARTED |
| M15 | Hosting / infrastructure vendors / renewals | M12, M13 | Asset-independent service catalogue and reminders; no provider API initially | NOT STARTED |
| M16 | Dashboards / reports / intercompany consolidation | M04, M06, M08, M09, M10 | Correct company permissions, traceable figures, elimination | NOT STARTED |
| M17 | Operations hardening / backups / rollout | F00 and delivered modules | Restore drill, E2E, accountant review, release approval | NOT STARTED |

## Phase / dependency sequence

**Phase 0:** F00 → F01/F02/F03, shared UI & staging foundations. Related scoped PRs: [EXECUTION_PLAN.md](EXECUTION_PLAN.md).
**Phase 1 (lead-to-cash vertical slice):** M01 → M02 → M03 → M04 → M08 using F02. Verify Egypt/Italy separation and accounting.
**Phase 2 (execution + people):** M05 → M06 → M07, then M10/M11, including employee reports.
**Phase 3 (recurrence + support):** M12 → M13 → M14 → M15, with auditable scheduled jobs.
**Phase 4:** M09 deeper accounting operations + M16 consolidated reporting + M17 acceptance/cutover.

These phases are **dependency groups**, not promises that statutory accounting/payroll could safely wait until after real billing. No live invoice/GL or payroll flows until accounting controls and legal review pass.

## Durable handoff rules
- Every module has a single tracked GitHub Issue (may be split into multiple implementation issues) and handoff file.
- Split a large module into small tasks `M01-01`, `M01-02` with explicit acceptance criteria, dependencies, code PRs and test evidence.
- Never mark a module `VERIFIED` merely because files or UI exist. Update only after repeatable tests and review.
- If a feature crosses backend and frontend, link BOTH PRs under the same Issue. Avoid duplicate module definitions in the second repository.

**2026-11-16 P0-01 merge evidence:** [backend **merged** PR #2](https://github.com/Elmo-Tech/erp-backend/pull/2), [frontend **merged** PR #1](https://github.com/Elmo-Tech/erp-frontend/pull/1), [backend CI PASS](https://github.com/Elmo-Tech/erp-backend/actions/runs/43858463818), [frontend post-merge CI PASS](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43860361496). F00 initial automated checks are **VERIFIED**; P0-01 Issue remains OPEN on unverified live browser SPA/API connectivity. F01/Auth is IN PROGRESS on draft PR #6 and frontend PR #2; security review/approval pending. No real-user deployments.
