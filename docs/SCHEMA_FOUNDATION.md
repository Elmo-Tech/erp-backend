# Phase 0 Schema Contract — first migration increment

## P0-03 verified CI and open gates — 2026-11-16 (latest evidence)

**Task IN PROGRESS, Draft [PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10) UNMERGED, Issue #8 OPEN.** Initial CI [#43919415460](https://github.com/Elmo-Tech/erp-backend/actions/runs/43919415460) **FAIL**: PostgreSQL trigger function persisted after Laravel `migrate:fresh` table reset; fixed with `CREATE OR REPLACE FUNCTION` in `93ba16a`. Next run [#43919771317](https://github.com/Elmo-Tech/erp-backend/actions/runs/43919771317) **FAIL**: new test used overlength `normal_balance='invalid'` (SQLSTATE 22001) instead of an in-range invalid value; corrected to `wrong` in `63afa0d`. These were test/integration failures, not security bypasses.

**Verified PASS on code commit `e7773d147dbc209721a43f2f3e2c0c3fe30dcae2`:** [Backend CI #43920097079](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920097079) SUCCESS: PostgreSQL 18 migration, **30 PHPUnit tests / 273 assertions**, P0-03 migration rollback/reapply, rollback-only least-privilege RLS probe, Docker build and container API health.

**Additional two-connection code** `cdecfa3` plus CI wiring `64b1e46d82c741de41eda6f87211a02f15082d41`: [CI #43920504958](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920504958) checked PostgreSQL 18 migration, PHPUnit, rollback/reapply, RLS pilot, and **two physical PostgreSQL connections / transaction-local company context reset / exclusion race** successfully at the step level. Whole-job Docker/final conclusion must be rechecked separately before stating overall PASS for this newer SHA.

**NOT RUN / NOT VERIFIED:** real PgBouncer transaction pooling, Laravel Octane/reused queue workers, cross-request concurrency under actual deployment and controlled tenant-context setter; immutable posting/accounting review, historical data upgrade and independent human sign-off. The SQL probe does NOT enable RLS in the app, and RLS custom GUC alone is NOT an authorization mechanism. SEC-01..08 Issue #7 open. ADR-022 PROPOSED only. **No merge/deployment/production role, user, or key changes.**

**Next Atomic Action:** verify completed full-job result of CI #43920504958 and the final docs-only commit CI; then implement an authenticated transaction-bound context integration covering actual PgBouncer/queue-worker reuse in a separately reviewed increment before considering production RLS. Keep PR #10 draft and Issue #8 open.

---


## P0-03 additive constraint implementation — 2026-11-16 (DRAFT PR #10)

Latest physical migrations were inspected on backend integration SHA `5bc57535129e652f24f4991e7b90d418cef6f200`. Company-owned finance parents already have same-company composite FKs in the appropriate child tables; P0-03 does not duplicate them. `2026_11_16_000006_harden_company_accounting_invariants.php` adds missing `UNIQUE(company_id,id)` for company role assignments, journal lines and idempotency requests; restrictive CHECK constraints on status/domain, valid debit-versus-credit, nonzero currency snapshot when present, positive line numbers, and timestamp coherency; indexed same-company FK lookups; `btree_gist` company-specific fiscal/period non-overlap; trigger guards on parent and child date boundaries. `audit_logs` and `outbox_events` remain nullable global/company-scoped; no fabricated company scope for global events.

This does **not** implement balanced/immutable posting, issued invoices, allowed period-state transitions, currency rounding/posting, or GL API. Those gates remain blocked pending F02 and review. PostgreSQL 18-only migration needs extension creation privilege; run against disposable DB first and confirm upgrade/rollback before any production migration. P0-03 migration/test CI is NOT VERIFIED as of this snapshot.

**Least privilege RLS pilot is opt-in and disposable only:** `database/security/p003_rls_pilot.sql` creates a `NOLOGIN NOBYPASSRLS` read-only role for `fiscal_years`, guards policy with transaction-local `app.company_id`, tests EG/IT directions and no-context behavior with savepoint rollback, then rolls back *everything*. Laravel application pool and production DB roles are untouched. This test cannot prove PgBouncer or real queue/worker safety; application membership checks remain authoritative. See [P0-03 handoff](handoffs/P0-03.md).

---


**Schema is partial by design.** It establishes the security and accounting-shaped base, not final tax posting or issued invoices.

## Implemented first tables
- Laravel identity: `users`, `sessions`, reset tokens (Laravel skeleton; user PK currently bigint; domain tables UUID).
- `currencies` ISO codes.
- `companies` UUID legal entities, base currency and timezone.
- `company_memberships` unique user/company membership; `company_role_assignments` roles tied to an actual membership.
- `fiscal_years` and `accounting_periods` scoped to company.
- `ledger_accounts`, `journal_entries`, `journal_lines` with same-company composite references.
- `audit_logs`, `outbox_events`, `idempotency_requests`.

## Important follow-up migrations BEFORE posting money
- PostgreSQL check constraints for line sides, fiscal/period non-overlap, status domains.
- A reviewed posting transaction/DB function that locks the period, checks all lines total exactly, enforces currency precision and forbids posting to closed periods.
- Immutable posted journal DB guards; source event idempotency; reversal journals.
- More granular privilege/grant model, protected payment and invoice links, authorized bank and tax ledgers.
- UUIDv7 must be emitted on all domain writes (no hard-coded/default production seeds).
- Audit append-only constraints and transactionally published outbox processor.
- Full company-scoped API authorization and least-privileged DB app role; optional tested RLS.
- No GL endpoints until all above gates and financial tests pass.

## Concrete relational invariants
- Company-owned parent tables expose unique `(company_id, id)`.
- Child rows reference that pair to prevent cross-company linkage.
- Project execution by other company is explicit, never a ledger access grant.
- No cascade-delete of any accounting/audit history.
- ISO 4217 currency code and decimal(20,4) amount; FX decimal(24,12).
- Time stored with timezone; effective legal dates separately as date.
- Opening balances and real production GL codes require accountant verification.

