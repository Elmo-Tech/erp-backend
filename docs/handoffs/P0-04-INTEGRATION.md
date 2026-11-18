# P0-04-INTEGRATION — A+B+FX financial-safety composition (test branch only)

> 2026-11-18 | Issue [#19](https://github.com/Elmo-Tech/erp-backend/issues/19), parent [#13](https://github.com/Elmo-Tech/erp-backend/issues/13) | [Draft PR #20](https://github.com/Elmo-Tech/erp-backend/pull/20) | **IN PROGRESS / never merge this rehearsal PR**.

## Provenance, branch isolation and exact inputs

| Scope | Original independent Draft HEAD | Original exact-head CI |
|---|---|---|
| P0-04A immutability guards #14 | `c39f4474d236c2902d15444911bbe340f146d5b4` | [#44308817619 PASS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44308817619) |
| P0-04B exact arithmetic and advisory eligibility #16 | `63d546a1bdf97e2369be4454e39f472a75104f9e` | [#44319472889 PASS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44319472889) |
| P0-04-FX validated NULL-safe SQL CHECK #18 | `efed18b596b1eab85d5ffd46b7d76f86f1e3dec5` | [#44324933224 PASS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44324933224) |
| Integration base | `21a085f5b101126cb6f535fdb03f204cb99d0405` | [#44115915862 PASS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44115915862) |

Branch `task/p0-04-composition-rehearsal` was created from the integration base only. Commits `c1f79eb` (A), `97c30bd` (B), `a1757eb` (FX) import unchanged source code and unique doc blobs by exact SHA and document original source heads in trailers. Shared `ci.yml`, `PROJECT_STATUS`, `ROADMAP`, `DECISIONS` were composed explicitly. **No original PR merged, rewritten, closed or modified.** Rehearsal branch only; no candidate production merge.

## Composition-specific edits / findings

- Database version order `2026_11_16_000006` P0-03 → `2026_11_17_000007` P0-04A → `2026_11_18_000008` FX. On test full `migrate:fresh`, all three may share one Laravel batch. P0-03 synthetic populated rollback script must use `--step=3`; FX-only replay uses `--step=1`. Prove actual state in PostgreSQL catalog.
- B unit/feature source validator remains unchanged. Its independent FX fixture previously inserted invalid NULL; new SQL CHECK rejects INSERT 23514, so adjust only the rehearsal test. Its independent test changed journal draft→posted, but A guard now forbids; the rehearsal test instead asserts that guard 23514 stops it and read-only eligibility leaves draft unchanged.
- A trigger `p004_guard_journal_line_mutation` returns 23514 early on a foreign-company journal reference; the prior FX and P0-03 tests expected 23503 from the later FK. Rehearsal tests assert guard 23514, then separately insert a line whose journal is own-company but account is foreign, asserting composite FK 23503. **This is a real composition discovery**, not an implicit change to either source PR.
- `tests/integration/p004_composition_eligibility_races.php`: actual P0-04B Laravel validator within an outer transaction and independent PDO session; while it holds header lock try child INSERT/UPDATE/DELETE/same-company reparent, expect timeout 55P03, verify rollback and retry, opposite header ownership, no status transition or audit/outbox/idempotency and no posting. Runs both direct PG18 and transaction-pooled PgBouncer.
- `tests/integration/p004_composition_acl_probe.php`: transactionally created synthetic restricted role; attempts TRUNCATE and trigger-disabling DDL, expects 42501. **This does not verify the real app database principal**; CI uses privileged `elmo` and PgBouncer `auth_type=trust`. No production ACL or DDL approval.
- Existing A/B/FX integration and PHPUnit suites remain intact apart from explicit rehearsal-only assertion alignment. Exact high-volume 0.0001 rejection uses string decimal / PostgreSQL NUMERIC. FX completeness remains fail-closed at SQL boundary with `pg_constraint.convalidated`.

## CI acceptance ledger

| Check | State / evidence |
|---|---|
| Prior individual A/B/FX full runs | PASS on separate SHAs; **not** composition proof |
| Initial composition CI [#44331223086](https://github.com/Elmo-Tech/erp-backend/actions/runs/44331223086) | **FAIL**: FX journal FK expected 23503; A guard first raises 23514 |
| Combined PG18 fresh + PHPUnit | **PASS** on `177ab035` [CI #44332634462](https://github.com/Elmo-Tech/erp-backend/actions/runs/44332634462) |
| Synthetic populated upgrade, rollback, reapply, SQLSTATE | **PASS** #44332634462 on `177ab035` |
| Direct PG18 + PgBouncer actual advisory validator/child writes | **PASS** #44332634462 on `177ab035` (no real posting) |
| DDL/TRUNCATE synthetic role probe | **PASS** #44332634462 on `177ab035`, **real runtime NOT VERIFIED** |
| Real runtime least privilege, true posting-vs-child tuple deadlock, real data/volume DDL budget | NOT RUN / NOT VERIFIED |
| Independent human security review and accountant sign-off | NOT RUN |
| Posting Service, Finance authorization, true source idempotency and atomic audit/outbox | NOT IMPLEMENTED |

**Complete code+docs SHA verification:** [CI #44332634462](https://github.com/Elmo-Tech/erp-backend/actions/runs/44332634462) **SUCCESS** on `177ab035b4ba2225ca2bc92f6cbff48df3c4594f`. Individual negative steps succeeded, including direct and pooled real eligibility attempts and synthetic ACL. This subsequent evidence-documentation commit requires a distinct exact-head run. Automation is not a human review.

## Merge readiness / explicit next action

No development integration merge recommendation while combined full CI or important security risks remain unresolved. Even if all synthetic CI passes, **no standalone review or owner authorization** exists; request qualified human source+DB/security/accountant review, obtain dedicated role ACL verification and review a deadlock-safe posting protocol. Only then ask for separate explicit owner permission for any individual development merge; **never merge the composition rehearsal PR itself**. ADR-024 PROPOSED; Issues #7/#8/#11 OPEN; PRs #14/#16/#18 Draft. No deployment, production data, real posting, `main`/`oberation` or Permanent RLS.
