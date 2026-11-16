# Phase 0 Schema Contract — first migration increment

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

