# Phase 0 Schema Contract — first migration increment

## P0-04-FX proposed post-P0-03 hardening (Draft PR #18, not integrated)

The P0-03 `p003_currency_snapshot_chk` can accept a missing FX field via PostgreSQL CHECK UNKNOWN. [Issue #17](https://github.com/Elmo-Tech/erp-backend/issues/17) adds a **separate** reversible `p004_fx_snapshot_complete_chk` on `journal_lines`: transaction currency, `amount_currency` and `fx_rate_snapshot` must be all NULL, or all NOT NULL with positive decimal amount/rate; explicit `IS TRUE` makes UNKNOWN invalid. Existing same-company composite FKs and currency FK remain. Migration refuses invalid historical rows without modifying them; full table scan and ACCESS EXCLUSIVE lock need separate human production DDL review. The new constraint has no relationship to posting authorization or status changes; no financial API or live postings are permitted.


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

