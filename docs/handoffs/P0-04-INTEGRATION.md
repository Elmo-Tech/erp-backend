# P0-04-INTEGRATION — A+B+FX financial-safety composition (test branch only)

## P0-04-REVIEW | independent merge-readiness source/log audit — 2026-11-18

**Scope:** engineering/code/log inspection only. **All original PRs #14/#16/#18: NO-GO for immediate development merge; #20: NEVER MERGE.** No submitted GitHub PR reviews or requested reviewers on any of #14/#16/#18/#20 at this checkpoint. This AI-assisted review is NOT an independent human security/DB/accountancy approval. Source integration HEAD remains `21a085f5b101126cb6f535fdb03f204cb99d0405`. Review-only documentation is committed on the isolated rehearsal branch; no changes to the original PR source branches or integration branch.

### Verifiable exact-head CI vs real assertions

| Scope | Exact SHA | GitHub Actions workflow | Reviewer finding |
|---|---|---|---|
| A #14 | `c39f4474d236c2902d15444911bbe340f146d5b4` | [#44308817619 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44308817619) | **CRITICAL TEST HARNESS GAP:** step called P0-03 synthetic upgrade/rollback printed **three failures** despite successful job (two `SQLSTATE 23503 cross-company FK`, one `p003_line_company_id_uniq down existence`). Log shows `--step=1` rolled back A `000007`, NOT P0-03 `000006`. Negative test process/step did not propagate failure. Do NOT credit this CI with successful P0-03 populated rollback. |
| B #16 | `63d546a1bdf97e2369be4454e39f472a75104f9e` | [#44319472889 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44319472889) | Standalone `--step=1` correctly removed P0-03 `000006`; no P0-03 fixture failure text in inspected logs |
| FX #18 | `efed18b596b1eab85d5ffd46b7d76f86f1e3dec5` | [#44324933224 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44324933224) | Standalone `--step=2` removed FX `000008` and P0-03 `000006`; populated P0-03 fixture PASS in inspected logs |
| A+B+FX #20 | `48748c432404f1256bb4837d1c090ae62de656b8` | [#44333487913 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44333487913) | Combined `--step=3` removed FX `000008`, A `000007`, P0-03 `000006`; explicit P0-03 `up/down/up` PASS, PG18/PgBouncer concurrency and Docker/API PASS. This is not isolated pairwise CI. |

**Required repair before even considering A development merge:** A-only CI fixture must rollback `000007` then `000006` on the disposable DB (appropriate `--step=2` on this source), verify the migrations/catalog and `up/down/up` sentinel assertions, distinguish 23514 trigger rejection from 23503 composite account-FK rejection, and **force nonzero process exit on every failed assertion**. Check shell step exit codes; `set -e` alone cannot help if PHP exit status is zero. Rerun full exact-HEAD CI on corrected original A PR, not just composition branch. Any future ordered integration must calculate rollback steps from its actual migration set, never copy the rehearsal script blindly.

### Compatibility matrix — evidence limited to the versions actually exercised

| Combination | Core-code compatibility | Isolated pair CI | Composition-only known change |
|---|---|---|---|
| A+B | **Observed inside triple PASS**: A blocks posted transitions while B advisory eligibility leaves draft/side effects untouched, locks header and does not take line tuple FOR UPDATE | **NOT RUN** alone | B-alone `test_non_draft_journal...` legitimately creates posted row on P0-03 base; combined test must expect A SQLSTATE **23514** instead |
| A+FX | **Observed inside triple PASS**: A trigger and validated FX CHECK coexist; FX incomplete tuples denied 23514; FK remains enforced | **NOT RUN** alone | FX/P0-03-alone foreign journal-link negative expects FK **23503**, but A trigger can deny earlier **23514**; separately verify opposite-company account with own-company journal returns **23503** |
| B+FX | **Observed inside triple PASS**: B exact decimal/PG NUMERIC and FX complete tuple compatible | **NOT RUN** alone | B-alone intentionally inserts partial FX to check B advisory denial; with FX present, SQL INSERT now fails first with **23514**. Keep original B-only test unchanged |
| A+B+FX | **PASS** on exact rehearsal `48748c43` / [#44333487913](https://github.com/Elmo-Tech/erp-backend/actions/runs/44333487913), direct PG18, PgBouncer transaction pooling, synthetic populated migrations, rollback/reapply, Auth/MFA, Docker | **YES, triple only** | Three-way test expectations are NOT portable verbatim into independent PR branches; neither posting nor external review was exercised |

**Source inspection:** A guard migration, B's two validator files and FX migration retain their original GitHub blob SHAs on #20. A's row-level `BEFORE` trigger rejects draft→posted/reversed and historical posted-row edits. Its comment "lock parent before touching a line" is NOT a proof of physical update ordering: PostgreSQL may lock the child tuple before the trigger acquires the header. B obtains header `FOR UPDATE`, company/fiscal/period/currencies/accounts `FOR SHARE`, then reads lines using PostgreSQL `NUMERIC`; this remains **advisory**, takes no child tuple row locks, no writes, and accepts caller-supplied companyId as a scope rather than verified Finance authorization. FX's predicate uses explicit NULL and `IS TRUE`, catalog/preflight validation, and a transactional validated `ADD CHECK`; rollback drops only the new check and intentionally allows the old UNKNOWN weakness again. `000006 → 000007 → 000008` is the required migration order.

### New runtime role finding (source-backed; not a production deployment assessment)

`compose.yaml` sets PostgreSQL container `POSTGRES_USER: elmo` **and** API `DB_USERNAME: elmo`. The official image initializes that DB principal as a privileged bootstrap superuser, so the supplied **local Compose runtime is NOT least-privileged** and can bypass row-trigger protection through DDL/trigger changes/TRUNCATE. `.env.example` also defaults to `elmo`; `config/database.php` selects DB username via env, without a separated read/write runtime role. CI similarly uses the bootstrap `elmo` principal; its synthetic `SET LOCAL ROLE` test gets 42501 correctly but does NOT prove runtime ACLs. This is a **blocker for any real ledger use** and requires a dedicated test-only, non-owner application role, explicit grants and runtime direct/PgBouncer negative tests. No production runtime credentials were accessed or tested.

### Lock safety / SQLSTATE / DDL risks

- **PASS narrowly:** B's actual validator vs independent child INSERT/UPDATE/DELETE/same-company reparent session observed `55P03` lock waiting in direct PG18 and PgBouncer; opposing writer-first wait/rollback also verified. No actual posting transaction, and no future posting-vs-child deadlock `40P01` was tested.
- **UNRESOLVED:** future parent→child `FOR UPDATE` while child `UPDATE` already holds a tuple then waits for parent creates a plausible lock cycle. Mixed period-close/account-deactivate/currency-change vs journal edit order needs adversarial, real two-session+pooled negative tests. **Do not implement or approve posting service based on advisory validation.**
- **FX DDL:** validated `ALTER TABLE ADD CHECK` takes `ACCESS EXCLUSIVE` + full scan. Disposable 3s lock/30s statement timeouts do NOT establish production-size lock budget; no production-like populated volume/blocked writers. A future staged `NOT VALID` then `VALIDATE CONSTRAINT` strategy needs separate human DB design approval. Synthetic historical preflight must fail without rewriting old financial rows; FX rollback reopens NULL weakness and needs explicit safe-operation decision.
- **SQLSTATE contracts:** guard/FX CHECK denial `23514`, same-company composite account FK violation `23503`, lock timeout `55P03`, synthetic restricted role ACL denial `42501`. Do not normalize those codes into one assertion. Deadlock `40P01` only as a **future unproven negative case**.

### Independent human gate and proposed order (NO merges approved)

1. **Database engineer (independent of authors):** inspect both migrations and `pg_constraint`/`pg_trigger`/function ACL state, validate rollback from real schema batch layout on synthetic populated EG/IT, enforce nonzero test exit, design reviewed DDL lock budget and FX rollback no-weakening policy; record signed PR review with blocker closure evidence.
2. **Security engineer (independent):** prove exact application DB login and role ownership/privilege model, negative `TRUNCATE`, `ALTER TABLE DISABLE TRIGGER`, `DROP TRIGGER`, `CREATE/REPLACE FUNCTION`, `SET ROLE` via direct and PgBouncer with a real disposable dedicated runtime principal. Audit trusted actor/company Finance scope, least privilege, no posting/API bypass; independent PR review. SEC #7/#8/#11 stay OPEN.
3. **PostgreSQL concurrency engineer:** validate A header/child ordering with two real connections; check period/account/line cycles, intentional `55P03` and `40P01` with bounded retries/rollback and starvation under PgBouncer. Future posting-service protocol remains separately gated and **NOT IMPLEMENTED**.
4. **Qualified Egypt/Italy accountant/finance owner:** sign off legal-company isolation, exact decimal (including 0.0001 imbalance), FX tuple/rate snapshot semantics, closed fiscal periods, reversal-only historical behavior and non-posting advisory boundaries. This must be documented independently of AI/CI.
5. **Only after fixes and these reviews, propose** a **separately owner-authorized** development integration order **A → FX → B**: A guard first, FX DB hardening next, B advisory decimal eligibility last. The next PR's tests/CI must be adapted for its *new integrated base* after each authorized merge and run on exact target+source candidate SHA; preserve standalone source-context negatives and add permanent combined-context tests. Alternate ordering requires its own matrix and sign-off. **No merges performed or authorized by this review.**

**Permanent dev-integration regressions needed after any authorized change:** `migrate:fresh`, populated EG/IT upgrade and correct-order down/reapply with explicit exit-code sentinels; historical posted immutability + direct status INSERT/UPDATE rejection; base/complete and partial FX INSERT/UPDATE with catalog convalidated; parent-trigger `23514` and independent FK `23503` in both EG/IT directions; exact huge totals with 0.0001 mismatch and float-input rejection; fiscal/account/currency state negatives; actual advisory validator no writes/status/audit/outbox; two sessions direct PG18 and PgBouncer child CRUD/reparent/period/account lock protocol, timeout/rollback; scoped real runtime ACL negative; Auth/MFA/session/queue and Docker/API health. **Do not introduce a Financial Posting API.**

**Current formal verdict:** A **NO-GO** (CI false-positive prerequisite + human review), B **NO-GO** (standalone code CI passes but human/role and integration-context tests outstanding), FX **NO-GO** (standalone code CI passes but DDL-lock/rollback + human/role and integration-context tests outstanding), #20 **NEVER MERGE**. ADR-024 **PROPOSED**, Security #7/#8/#11 **OPEN**. No release, deployment, real financial data, permanent RLS, `main`/`oberation` changes, or owner approval inferred. Next action: repair A's misleading CI/rollback harness on its own PR after explicit scope approval and obtain independent DB/security/accountant reviewers, then refresh the GO/NO-GO with fresh exact-head evidence.

---


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
