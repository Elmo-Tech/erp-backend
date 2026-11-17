## ADR-022 — P0-03F exact-head evidence and proposed P0-03G decision gate (2026-11-17)

**CONFIRMED engineering PASS, NOT security release approval.** The task branch pre-handoff HEAD `93310fd8a302b186a895c744dcf99ae7b1862d87` completed [Backend CI #44069798799](https://github.com/Elmo-Tech/erp-backend/actions/runs/44069798799) with **SUCCESS on 38/38 workflow steps** including PostgreSQL 18/PHPUnit/migrations/rollback, real transaction-pooled PgBouncer, restricted Laravel Web/MFA/Session/Queue SQL principals, first MFA enrollment/TOTP/recovery one-time/Login/Logout, Egypt ↔ Italy authorization, concurrent membership revocation, five queue jobs on a reused Laravel worker, synthetic MFA Recovery INSERT failure/atomic rollback, Docker build and API health. The immediately preceding runtime-code HEAD `dbc0f7efac700a3fabb11d270df14977481c6b98` passed [CI #44069174715](https://github.com/Elmo-Tech/erp-backend/actions/runs/44069174715). A GitHub commit comparison confirmed that `dbc0f7e..93310fd` modified only `docs/DECISIONS.md`, `docs/PROJECT_STATUS.md`, `docs/handoffs/P0-03.md` and `docs/security/P0-03F_RUNTIME_ROLE_INTEGRATION.md` — **no runtime changes after the tested code**. This new handoff docs commit must obtain its own exact-SHA CI; earlier runs do not automatically validate it.

**Still OPEN / NOT SECURITY SIGNED OFF:** split SQL connections all live inside the same Web PHP process; that process can access MFA credentials and decrypt factors if fully compromised. `WebUser` intentionally reads password hashes and is vulnerable to their exposure by arbitrary Web SQL. The CI-only `public.p003f_lock_authorization_scope(bigint,uuid)` SECURITY DEFINER helper uses fixed SQL and qualified tables, but accepts caller IDs under `EXECUTE` by the Web principal; function privileges, ownership/search_path and intentional lock/availability abuse require independent qualified review. A custom writable `app.company_id` GUC is **NOT** a trusted DB tenant identity and RLS remains **OFF**. SEC-01..08 account activation/reset/key/session/security operations remain [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7) OPEN; [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8) OPEN; [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10) OPEN/UNMERGED with **no recorded formal human review**. ADR-022 is still **PROPOSED** (engineering recommendation: DEFER permanent RLS / Trusted Issuer). Octane, nonempty production-like upgrade, production privilege configuration and human security acceptance NOT VERIFIED.

**P0-03G next action — DESIGN / TEST FIRST:** independently threat-model Web-process compromise vs SQL-only injection; define whether a separate MFA command service or isolating identity process is warranted. Its factor keys/DB credentials must not be accessible to Web, commands must be specifically authorized and bound to user-owned challenges, rate limited/idempotent/audited, and sensitive recovery/enrollment/reset and compromised/replayed service credentials fail closed. Evaluate real deployment/ops cost; **a broker trusting Web-supplied user_id alone is not a security boundary**. Then review the SECURITY DEFINER lock helper and complete cross-role grants. Implement a smallest safe spike only on task branches/CI with synthetic identities and exact-head proof. If a strong process boundary is not feasible, state residual risk and track it as a pre-release gate rather than calling it solved or building an untrusted signer. Keep production release separate from any future non-production merge authorization.

**NO MERGE / DEPLOY / PERMANENT RLS / REAL USERS / KEY ROTATION / main / oberation actions authorized.** Historic snapshots below remain for audit; this status applies to the stated pre-handoff SHA, not future changes.

---

## ADR-022 verified P0-03F engineering evidence — 2026-11-17 (REMAINS PROPOSED)

[CI #44069174715 FULL SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44069174715) at exact code `dbc0f7efac700a3fabb11d270df14977481c6b98` covers restricted Web/MFA/Session/Queue actual Laravel operations, PG18/PgBouncer, MFA enrollment/TOTP/recovery/rollback, EG↔IT, concurrency/revocation, reused worker and Docker. **Engineering PASS is not security approval**. A dedicated DB connection inside the same Web PHP process is NOT secret or command isolation. The narrow SECURITY DEFINER lock helper needs independent review, as does process credential custody. **ADR-022 is still PROPOSED; recommendation DEFER permanent RLS and avoid a Trusted Issuer until independently justified**; no approved release change. Security issue #7 OPEN, task Issue #8 OPEN, PR #10 DRAFT, no merge/deploy/production/main/oberation. This subsequent documentation commit requires its own exact-head CI.

---

## ADR-022 P0-03F CI evidence — 2026-11-17 (PROPOSED, NO DECISION APPROVAL)

Engineering split-role tests **FULL SUCCESS** on code `d7b3114` at [CI #44068168985](https://github.com/Elmo-Tech/erp-backend/actions/runs/44068168985): PostgreSQL 18 and PgBouncer Web/MFA/Session/Queue real Laravel operations, enrollment/recovery/TOTP/login/logout, EG↔IT, five reused jobs, migrations/rollback/Docker. Later revocation race `e8f7201` requires independent exact-head CI. This is not independent human security review or production proof. **Remain PROPOSED; recommendation DEFER permanent RLS / Trusted Issuer.** No MFA process isolation, issuer or production signoff. PR #10 Draft, Issues #8/#7 OPEN.

---

## ADR-022 P0-03F addendum — 2026-11-17 (PROPOSED, NOT APPROVED)

**DEFER permanent RLS and Trusted Issuer.** P0-03F tests narrowed SQL role privileges and introduced projected Web credentials, dedicated MFA/Session/Queue connections, atomic MFA enrollment, and a PostgreSQL SECURITY DEFINER lock in *disposable CI only*. This does NOT provide independent MFA process/secret isolation; the same compromised PHP process can use MFA credentials. SQL injection may still read Web password hashes and selector tables. Independent human review of the helper and MFA security boundary is mandatory before any deployment. [P0-03F role/SQL ledger](security/P0-03F_RUNTIME_ROLE_INTEGRATION.md). Earlier P0-03E docs CI [#44044766275](https://github.com/Elmo-Tech/erp-backend/actions/runs/44044766275) passed at `ed90ca6`, not this runtime integration. ADR remains PROPOSED; PR #10 DRAFT; Issues #8/#7 OPEN; no merge/deploy/permanent RLS/production/main/oberation changes.

---

## P0-03E exact-HEAD CI confirmed / P0-03F runtime integration intake (2026-11-17; NEWEST CHECKPOINT)

**P0-03E engineering CI = FULL SUCCESS, SECURITY RELEASE = NOT APPROVED.** Exact tested task HEAD `3ac66658fb1508893fa3e58a6d98491f2ac24f3a` passed [GitHub Actions #44043335598](https://github.com/Elmo-Tech/erp-backend/actions/runs/44043335598): PostgreSQL 18 migrations/PHPUnit/rollback, existing EG↔IT tests/PgBouncer concurrency/worker reuse, P0-03D disposable probes, all **four P0-03E isolated SQL principals** verified on direct PostgreSQL and actual transaction-pooled PgBouncer, Docker build, API health. A later docs-only SHA needs its **own** CI; never attribute this result to it.

**Important scope:** `p003e_web`, `p003e_mfa`, `p003e_queue`, `p003e_session` are CI-only permission fixtures. **Auth, initial MFA enrollment, TOTP/recovery login and session/queue operations have NOT been exercised with the proposed split credentials inside Laravel.** `AuthController::login` still hydrates `User::query()->first()` (not viable with column-scoped `p003e_web`), `AuthenticatedDatabaseContext` uses default DB connection and row-level `lockForUpdate()`, `MfaService` updates factor/recovery state on default connection. Splitting `SESSION_CONNECTION` and `DB_QUEUE_CONNECTION` is supported by configs but not yet tested end-to-end under new credentials.

**Security decision remains PROPOSED:** **DEFER permanent RLS and do not implement a trusted issuer now**; custom `app.company_id` GUC is spoofable, and owner-simulated HMAC probe is not end-to-end issuer. Require Laravel-verified membership/role/permission, explicit company filters, composite FKs, least-privilege role partition and negative tests. A separate MFA DB connection inside the same compromised Web process is **not by itself process/secret isolation**; identify and test the actual threat boundary before claiming it. Existing `p003e_web` password-hash SELECT is still sensitive even if MFA columns are denied.

**P0-03F next atomic step:** (1) inventory exact Auth/MFA/session/queue SQL and grants; (2) add explicit Auth/Web user projection or mediated verifier to avoid all-column User hydration, without silently breaking password/MFA; (3) use distinct scoped connections/credentials for Session/Queue and a protected MFA command boundary (not broadly accessible to arbitrary Web SQL); (4) retain concurrency-safe revocation and MFA enrollment transaction atomicity while removing unsafe Web `UPDATE(updated_at)` grants; (5) run full first-time MFA, TOTP/recovery, logout, EG↔IT, revoked membership, replay/parallel HTTP, PgBouncer and 5-job worker negatives. Record all FAIL/NOT RUN honestly; Octane and independent qualified human review remain NOT VERIFIED.

**Governance:** [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10) OPEN/UNMERGED; [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8) and [P0-02 security Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7) OPEN. ADR-022 PROPOSED and no final security sign-off. No merge, persistent RLS, deployment, real credentials, production account activation, `main` or `oberation` changes without separately authorized decision.

---

## ADR-022 — P0-03E disposition (2026-11-17; PROPOSED, NOT APPROVED)

**Decision proposal: DEFER persistent RLS and DO NOT implement Trusted DB Issuer in this phase.** Application authorization plus explicit company-scoped queries, existing composite FKs, and increasingly narrow DB privileges are the interim design. A custom writable GUC cannot authenticate actors; an issuer is justified only if DB-enforced tenant identity against arbitrary runtime SQL becomes a requirement and independent provenance can be proven. No owner/security acceptance or permanent RLS activation is implied. The SQL role partition is a disposable CI proposal, not real Laravel integration.

- **Verified baseline:** [CI #43941237508](https://github.com/Elmo-Tech/erp-backend/actions/runs/43941237508) **SUCCESS** at exact previous documentation HEAD `3eb340f08941827b03ce3c425a6669b726c9836b` (PG18/PgBouncer/migrations/rollback/CI Auth/queue/isolation/Docker). It must **NOT** be attributed to P0-03E changes.
- **New P0-03E files:** `database/security/p003e_role_partition_disposable.sql`, `database/security/p003e_role_partition_checks.sql`, and CI `.github/workflows/ci.yml`; test-only four SQL principals through PG18 direct and PgBouncer. **New CI: NOT VERIFIED pending exact-head result**; no claimed Laravel workflow integration.
- **Real Auth/MFA under proposed principals: FAIL / NOT COMPATIBLE as currently implemented**: unscoped Eloquent user reads and `lockForUpdate` need access the new Web role deliberately denies; separate MFA operations and session/queue connections are not yet wired. The old p003c role's MFA grant audit deliberately expects denied writes. No blanket grants to bypass.
- **Open:** EG↔IT app tests under each new role; forged/replayed issuer claims (no production issuer); external permission/key custody; role revocation/concurrency under new principal architecture; production-like upgrade; independent human reviewer. Issues [#8](https://github.com/Elmo-Tech/erp-backend/issues/8) and [#7](https://github.com/Elmo-Tech/erp-backend/issues/7) OPEN, PR [#10](https://github.com/Elmo-Tech/erp-backend/pull/10) DRAFT. ADR-022 PROPOSED.
- **Next atomic action:** inspect exact new CI; repair any failing disposable role probes; then refactor scoped Web auth projection and dedicated isolated MFA/session/queue connections, replacing unsafe timestamp UPDATE/row lock interaction only with evidence-backed serialization tests. Require a qualified independent HUMAN review before release approval.
- **Forbidden:** no Merge, Deployment, production or real credentials, permanent RLS, `main`, `oberation`.

---

# Architecture Decision Register — approved 2026-11-16

## ADR-022 implementation evidence / P0-03E open decision gate (2026-11-16; CURRENT)

**P0-03D CODE/CI VERIFIED — P0-03 SECURITY NOT SIGNED OFF.** On Backend [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10), exact pre-note HEAD `b694bfbf97e88046bdd28e91e476eff3bd4eb521` completed [GitHub Actions #43937073189](https://github.com/Elmo-Tech/erp-backend/actions/runs/43937073189) **FULL SUCCESS**. Earlier P0-03D code `a4281bb21b3d3e5ddf56c94a56be9f710d60c2e6` completed [#43936400738](https://github.com/Elmo-Tech/erp-backend/actions/runs/43936400738) **FULL SUCCESS**. A newer documentation-only commit must obtain its own result; these results cannot be attributed to its SHA.

- **Covered in disposable synthetic CI:** PG18 migrations and rollback/reapply, PHPUnit, PgBouncer transaction pool, constrained runtime GRANT negatives, EG↔IT HTTP/SQL and session/queue isolation, 5 jobs in one worker, concurrent membership revocation, rollback-only signed-claim experiment against GUC/claim tampering, Docker build and API health.
- **Unresolved security findings:** mutable `app.company_id` bypasses GUC-only RLS; a real Laravel→DB privileged claim issuer and authenticated provenance do NOT exist; owner-generated HMAC in CI does not establish replay resistance, safe issuer permissions or external/compromised SQL denial. CI role SELECT all `users` columns (including password hashes/MFA secret); `UPDATE(updated_at)` allows timestamp edits on four auth/company tables; session/queue DML broad and MFA flows **FAIL** under restricted runtime grants by design. Do not grant unscoped MFA access merely to make tests pass.
- **Recommendation:** keep trusted Laravel membership/permission validation and explicit company-scoped SQL predicates with composite FK checks. RLS is OPTIONAL defence in depth only after a separately justified, independently verified DB-identity boundary; avoid implementing a signer whose signing capability is accessible to the same arbitrary-SQL runtime principal.
- **P0-03E Next Atomic Action:** (1) qualified independent reviewer challenges threat model, GUC spoofing, stolen/reused signed claims and GRANT/SECURITY DEFINER/search_path boundaries, or owner records a limited non-production acceptance without implying human sign-off; (2) inventory application table/column/query needs (Auth, MFA, queue, sessions, companies, eventual finance); (3) choose a feasible trusted-issuer/role-separation architecture or explicitly defer RLS and ship application scoped-only DB hardening; (4) implement adversarial PG18/PgBouncer/HTTP/queue tests and verify actual runtime Auth/MFA behaviour; (5) re-run exact-head CI and document findings.
- **Gates:** ADR-022 **PROPOSED**; PR #10 **DRAFT/UNMERGED**; [Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8) and [security Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7) **OPEN**. No permanent RLS, real user credential, production/staging deployment, main/oberation change or PR merge authorized in this note.

**Older CI-pending notes below refer to historical commits. Latest verified result is #43937073189 on `b694bfb`; current documentation update needs a fresh run.**

---
## P0-03D exact-code CI verified — 2026-11-16 (LATEST CHECKPOINT)

[**CI #43936400738 FULL SUCCESS**](https://github.com/Elmo-Tech/erp-backend/actions/runs/43936400738) at **code SHA `a4281bb21b3d3e5ddf56c94a56be9f710d60c2e6`**, not at the later docs HEAD. PostgreSQL 18 full PHPUnit/migration checks, migration rollback/reapply, P0-03C constrained runtime/PgBouncer/parallel HTTP/revocation/queue checks, the **P0-03D direct+pooled runtime GRANT audit**, **rollback-only signed-claim spoofing probe**, Docker image build and container API health completed successfully. [#43936382989 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/43936382989) at the prior synthetic fixture fix `a811416`.

**Interpretation:** these passing tests demonstrate behavior only in disposable CI with synthetic identities and an owner-simulated MAC issuer. **No complete unforgeable authenticated Laravel → PostgreSQL issuer exists**; MFA/Auth least-privilege gaps, broad session/queue permissions, MAC replay/delegation/issuer isolation and human review are unresolved. Writable GUC-only RLS remains a demonstrated **FAIL** and **ADR-022 remains PROPOSED**. Later documentation-only SHAs require their own CI verification; do not attribute this run to them. Draft PR #10 / Issues #8 and #7 remain open, no merge/deploy/RLS production changes.

**Next:** independent qualified human review of the trust-boundary packet at exact current SHA, then separately design and test a real trusted issuer with scoped DB capabilities and Auth/MFA/queue privilege partitioning; obtain separate owner approval before any RLS or integration.

---


## P0-03D implementation verification addendum — 2026-11-16

**P0-03D source commits:** `878e80f` initial rollback-only signed-claim probe; `adde8cd` verifier privilege-check correction; `e873a27` CI wiring; `08f4fce` review packet; `784411d`, `2915a89`, `cf6a585`, `ee0de47` status/handoff/ADR/roadmap; `dd6385d` diagnostics; `f3d492d` runtime GRANT-negative audit; `a811416` isolated EG fixture repair; `a4281bb` CI direct-PG/PgBouncer GRANT audit. Inspect `git log` for exact SHAs.

**Failure + correction evidence:** [CI #43935671594 FAILURE](https://github.com/Elmo-Tech/erp-backend/actions/runs/43935671594) and [#43936123774 FAILURE](https://github.com/Elmo-Tech/erp-backend/actions/runs/43936123774) exposed `eg_signed_scope` failure because earlier revocation-race CI permanently revoked the synthetic EG membership *inside its disposable runner*, not because a forged claim succeeded. `a811416` restores only that synthetic membership within the signed-probe `BEGIN … ROLLBACK`. No persistent business record changed.

**New P0-03D step-level evidence:** [CI #43936400738](https://github.com/Elmo-Tech/erp-backend/actions/runs/43936400738), code SHA `a4281bb21b3d3e5ddf56c94a56be9f710d60c2e6`: runtime GRANT audit (direct PostgreSQL and actual transaction-pooled PgBouncer) **STEP PASS**, signed-claim rollback-only spoofing tests **STEP PASS**, previous direct/pooled role boundary and parallel HTTP/revocation tests **STEP PASS**. Full-job Docker/health and any later documentation HEAD CI must be checked separately before claiming FULL SUCCESS. These are CI tests using synthetic actors and an owner-simulated issuer; not full signed identity provenance.

**Security verdict remains NO SIGN-OFF:** GUC-only RLS is a reproduced FAIL. A valid user/company MAC signed in a single owner-controlled transaction is a proof of concept and does not establish that a deployable Laravel trusted issuer cannot be spoofed, replayed, or compromised. Restricted runtime MFA workflows remain incompatible without reviewed grants/function isolation. Human security review, Octane and production-like migration still NOT VERIFIED. ADR-022 PROPOSED; PR #10 Draft; Issues #8/#7 OPEN.

**Next:** commission an independent qualified reviewer to inspect [packet](security/P0-03D_TRUST_BOUNDARY_REVIEW.md), challenge issuer and privilege model, then build a separately approved trusted issuer implementation and negative end-to-end tests before any RLS approval.

---


## ADR-022 P0-03D addendum — 2026-11-16 (PROPOSED; no security decision approved)

**Proposed preference:** explicit trusted Laravel actor/membership/permission validation and mandatory company-scoped predicates with existing company FK invariants and narrowly allowed DB access. Optional RLS can only be defence-in-depth unless a separate trusted DB-side identity has been independently demonstrated. GUC-only RLS under `p003c_runtime` is *known vulnerable* via `set_config`; cannot be accepted as an authorization boundary.

**Alternatives and experimental evidence:** (a) application predicates plus strict grants, (b) GUC-only RLS — rejected for authorization, (c) owner-held HMAC-signed transaction claim verified by constrained SECURITY DEFINER — **research-only rollback SQL**, with no deployed issuer, proof of end-to-end non-forgery or replay prevention, (d) per-company roles/DBs — stronger SQL separation but operational burden and would change ADR-003. See [security packet](security/P0-03D_TRUST_BOUNDARY_REVIEW.md) for trust diagram, attack capabilities, full grant matrix, negative tests and independent human review criteria.

**Grant blocker:** `UPDATE(updated_at)` on `users`, `companies`, `company_memberships`, `company_role_assignments` supports `SELECT ... FOR UPDATE` but allows timestamp tampering; broad SELECT users exposes password and MFA secret; restricted runtime lacks essential MFA enrollment/recovery/user mutation GRANTs, and queue/session DML requires careful role partitioning. No production-wide GRANT approval. New probe/CI result must be checked on exact SHA, and qualified independent human security reviewer must explicitly sign off. **ADR-022 remains PROPOSED; Issue #7 open; PR #10 stays Draft, no merge/deploy/RLS enablement.**

---


**Final code CI verification:** [#43920504958](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920504958) **FULL JOB SUCCESS**, head `64b1e46d82c741de41eda6f87211a02f15082d41`: PostgreSQL 18 migrations, PHPUnit **30 tests/273 assertions**, P0-03 rollback/reapply, disposable RLS limited role/transaction-local context, two physical-session lock/exclusion/no-leak smoke, Docker build and HTTP health. This supersedes the prior step-level-only assessment. [#43920097079](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920097079) separately FULL SUCCESS on pre-two-session commit `e7773d1`. [Docs-only HEAD CI #43920822054](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920822054) had not completed at this record; any later docs-only commit requires independent CI check.

**Still NOT RUN:** real PgBouncer transaction pool and Laravel reused worker/Octane integration, authenticated tenant-context propagation under actual HTTP/queue workload, cross-session privilege escalation via malicious GUC, production-like migration on nonempty legacy data, independent review. RLS not activated and no production release. **Next Atomic Action:** implement and test a trusted membership-validated `SET LOCAL` transaction wrapper with actual PgBouncer/worker reuse, prove cross-request EG↔IT no-leak and bypass negatives, and submit a narrowly scoped reviewed PR before any RLS enabling decision.



## P0-03 verified CI and open gates — 2026-11-16 (latest evidence)

**Task IN PROGRESS, Draft [PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10) UNMERGED, Issue #8 OPEN.** Initial CI [#43919415460](https://github.com/Elmo-Tech/erp-backend/actions/runs/43919415460) **FAIL**: PostgreSQL trigger function persisted after Laravel `migrate:fresh` table reset; fixed with `CREATE OR REPLACE FUNCTION` in `93ba16a`. Next run [#43919771317](https://github.com/Elmo-Tech/erp-backend/actions/runs/43919771317) **FAIL**: new test used overlength `normal_balance='invalid'` (SQLSTATE 22001) instead of an in-range invalid value; corrected to `wrong` in `63afa0d`. These were test/integration failures, not security bypasses.

**Verified PASS on code commit `e7773d147dbc209721a43f2f3e2c0c3fe30dcae2`:** [Backend CI #43920097079](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920097079) SUCCESS: PostgreSQL 18 migration, **30 PHPUnit tests / 273 assertions**, P0-03 migration rollback/reapply, rollback-only least-privilege RLS probe, Docker build and container API health.

**Additional two-connection code** `cdecfa3` plus CI wiring `64b1e46d82c741de41eda6f87211a02f15082d41`: [CI #43920504958](https://github.com/Elmo-Tech/erp-backend/actions/runs/43920504958) checked PostgreSQL 18 migration, PHPUnit, rollback/reapply, RLS pilot, and **two physical PostgreSQL connections / transaction-local company context reset / exclusion race** successfully at the step level. Whole-job Docker/final conclusion must be rechecked separately before stating overall PASS for this newer SHA.

**NOT RUN / NOT VERIFIED:** real PgBouncer transaction pooling, Laravel Octane/reused queue workers, cross-request concurrency under actual deployment and controlled tenant-context setter; immutable posting/accounting review, historical data upgrade and independent human sign-off. The SQL probe does NOT enable RLS in the app, and RLS custom GUC alone is NOT an authorization mechanism. SEC-01..08 Issue #7 open. ADR-022 PROPOSED only. **No merge/deployment/production role, user, or key changes.**

**Next Atomic Action:** verify completed full-job result of CI #43920504958 and the final docs-only commit CI; then implement an authenticated transaction-bound context integration covering actual PgBouncer/queue-worker reuse in a separately reviewed increment before considering production RLS. Keep PR #10 draft and Issue #8 open.

---


## ADR-022 — PostgreSQL company isolation and optional RLS context (PROPOSED; 2026-11-16)

### Current evidence / P0-03D decision gate (2026-11-16)

**Status remains PROPOSED — NO RLS ACTIVATION OR PRODUCTION ACCEPTANCE.** [CI #43932289785](https://github.com/Elmo-Tech/erp-backend/actions/runs/43932289785) **FULL SUCCESS** at `0ae9e61b0407bb336703b5c01ab34b1363be1310`: disposable non-owner DB role, PgBouncer, active-transaction revocation, EG/IT parallel HTTP, reused queue worker, PostgreSQL migrations, PHPUnit, Docker/health. [CI #43931902210](https://github.com/Elmo-Tech/erp-backend/actions/runs/43931902210) previously FULL SUCCESS at code commit `3ec9796`. Later docs-only HEADs need independently verified CI.

**Unresolved threat:** any actor able to execute SQL as the shared runtime credential can write custom `app.company_id` and defeat GUC-only RLS before an application end-of-transaction check. `NOBYPASSRLS` alone does not repair that trust boundary. Also `SELECT ... FOR UPDATE` currently relies on explicitly granted `UPDATE(updated_at)` for four auth/company tables, a real writable permission; audit full production GRANT/default privilege/owner/MFA/session/queue footprints. Independent security review **NOT COMPLETED**.

**P0-03D design decision required (not approved here):** document attacker capabilities, effective DB authentication principal, tenant identity provenance, how tenant context can or cannot be forged, fail-closed SQL write/read tests, alternative architectures, pool/worker handling and migration rollback. Treat RLS as OPTIONAL defence in depth unless a genuinely trusted DB-side mechanism is independently demonstrated. No permanent policy, runtime production credentials, merge or deployment is authorized.

### P0-03C evidence amendment — PROPOSED; no RLS activation (2026-11-16)

A separate **LOGIN** `p003c_runtime` database principal is proposed and created **only inside disposable CI** following migrations by `elmo` owner. CI test script prohibits superuser, createdb, createrole, inheritance, bypassrls, schema creation, owner membership, financial table SELECT and membership writes. Grant scope remains an explicitly reviewed allowlist, including auth/company lookup SELECT and session/queue DML. This is narrower than the prior exploratory NOLOGIN probe (which is NOT the application's executable credential). Its CI-only password is synthetic and must never become a deployment credential; future privileged provisioning and rotation need separate approval.

A rollback-only RLS trial on `fiscal_years` demonstrates both EG→IT / IT→EG filtering **and** a malicious tenant switch using `set_config('app.company_id', ...)` by the non-owner restricted role. This establishes that `NOBYPASSRLS` and `NOINHERIT` do not make writable custom GUCs trustworthy. Even when `AuthenticatedDatabaseContext` locks/reauthorizes rows, SQL that directly changes the GUC before a read can leak data. Database-side trusted, non-forgeable provenance and carefully restricted privileged functions require a separate threat model; no app RLS or security-definer function is approved. All current company data reads still require an independently authorized literal company predicate. Concurrent revocation should serialize with acquired row locks and deny new transactions; owner/membership cancellation *mid-active transaction* cannot automatically invalidate reads already authorized inside that transaction. Revocation SLA and long-running transactions need operational definition.

New code commits [0aba57c](https://github.com/Elmo-Tech/erp-backend/commit/0aba57c98cebfbbad53781c4bf3753fe9b16435a), [dadf6ac](https://github.com/Elmo-Tech/erp-backend/commit/dadf6ac0d5d126144c8bde690783a9e8ad5d6062); CI [#43930838117](https://github.com/Elmo-Tech/erp-backend/actions/runs/43930838117) and [#43931060344](https://github.com/Elmo-Tech/erp-backend/actions/runs/43931060344). Octane NOT RUN because dependency/server wiring is absent; HTTP multiprocess test is not Octane. **Decision still PROPOSED, Issue #7 stays open, separate approval needed before RLS/merge/deploy.**

### P0-03C corrected runtime privilege and CI evidence addendum (2026-11-16)

Code fixes after original role commit: [6a99a1c](https://github.com/Elmo-Tech/erp-backend/commit/6a99a1c538b4ad52e41a43cfa152d7a501c018f7) handles empty Sanctum 204 responses; [0e1b575](https://github.com/Elmo-Tech/erp-backend/commit/0e1b575e82281155da372a513adaaf1f145ffbc8) and [3ec9796](https://github.com/Elmo-Tech/erp-backend/commit/3ec9796bc16560a1fc6474d2fc15ef38067f3872) grant UPDATE of **updated_at ONLY** on users, companies, company_memberships, company_role_assignments because PostgreSQL `SELECT ... FOR UPDATE` requires UPDATE privileges on locked tables (including joined companies). **Not read-only credentials:** these four timestamp columns can be modified by the runtime role; all status/company/role columns remain denied. Explicit tests exercise forbidden membership status mutation. This is a CI-limited proof, **not** a complete approved least-privilege production grant matrix or verified MFA enrollment/recovery runtime. Review timestamp-write risk, privilege partitioning and all API/queue paths before treating role as deployable.

[#43931060344](https://github.com/Elmo-Tech/erp-backend/actions/runs/43931060344) failed only the newly added HTTP probe before the 204/parser fix. [#43931343718](https://github.com/Elmo-Tech/erp-backend/actions/runs/43931343718) reproduced the missing `FOR UPDATE` users UPDATE grant. [#43931678496](https://github.com/Elmo-Tech/erp-backend/actions/runs/43931678496) then reproduced the joined-companies lock privilege. The current role/HTTP/revocation steps passed on [#43931902210](https://github.com/Elmo-Tech/erp-backend/actions/runs/43931902210) at code commit `3ec9796`; full-job Docker/health conclusion must be checked before marking the entire run SUCCESS. The legacy P0-03B tests and migrations in the same run also completed successfully. No new RLS approval.



**Status: PROPOSED ENGINEERING DESIGN ONLY — NOT APPROVED FOR PRODUCTION.** Cross-company physical integrity derives from already-approved ADR-003 and ADR-004. This ADR records new implementation choices for independent review, not a silent owner sign-off.

**Proposal:** keep `company_id NOT NULL` on legal rows and composite same-company foreign keys, with additive parent candidate keys; enforce safe accounting domains, positive single-sided journal lines, date containment and company-scoped exclusion ranges in PostgreSQL 18. No posting APIs or immutable journal assertions are claimed. Allow nullable company_id only on explicitly global audit/outbox events and global user/MFA/currency entities, never as a loophole to write legal ledger rows.

**RLS option under evaluation:** a distinct NOLOGIN/NOBYPASSRLS, non-table-owner least-privileged runtime role can use `current_setting('app.company_id',true)` only after *trusted membership authorization* and per-transaction `SET LOCAL`, and only for a transaction encompassing all company SQL. Missing tenant context must return zero legal rows. Do NOT allow clients to select arbitrary company context by directly writing a GUC: RLS based solely on a writable custom GUC is not authorization. Global auth, user login/MFA and cross-company consolidation flows need separate vetted context boundaries. A rollback-only disposable fiscal_years SELECT policy probe is included in P0-03; no application RLS is activated.

**Alternatives:** application-only scoping (insufficient defence in depth), unconditional RLS now (unsafe for unaudited session/worker pool paths), security-definer context functions (require separate threat modeling), and database-per-company (greater operational overhead, conflicts with chosen ADR-003 shared schema). **Decision gate:** prove actual Laravel/queue/PgBouncer transaction-pool behavior, separate app/migration roles and GRANT matrix, authorized context provenance, concurrency and role-bypass negatives before requesting explicit approval for durable RLS. No production roles/key/secret changes authorized.

**Evidence:** [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8), [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10), [handoff](handoffs/P0-03.md). Base CI [#43917199722](https://github.com/Elmo-Tech/erp-backend/actions/runs/43917199722) PASS; new P0-03 CI NOT VERIFIED in this snapshot. Production blockers SEC-01..08 remain open in [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7). No Merge/Deploy approval.

---


### Verified P0-03B PgBouncer/worker experiment (ADR-022 STILL PROPOSED)

Backend-only implementation uses a fresh, lock-protected live actor/membership/permission check immediately before transaction-local GUC setup. GUC values remain freely writable by a client with SQL access; CI's rollback-only role probe deliberately demonstrates tenant-switch read via `set_config` even with an RLS policy tied to the GUC. App callbacks must explicitly filter company-owned rows; final GUC check cannot prevent earlier unauthorized reads made by malicious SQL. Pooled Laravel connections require opt-in `DB_PGBOUNCER_TRANSACTION=true` to enable PDO emulated prepares and avoid PostgreSQL server-prepared statement persistence problems between PgBouncer backend transactions. [CI #43927365985](https://github.com/Elmo-Tech/erp-backend/actions/runs/43927365985) FULL SUCCESS at `1912770`: five authenticated-context tests (24 assertions), two parallel pooled clients (24 transactions), single reused worker executing five jobs with mid-session membership revocation, old 30 PHPUnit tests (273 assertions), original schema/RLS/rollback/concurrency, Docker health. This experiment **does not approve RLS**, an app runtime least-privilege database role, merge or release. Explicitly require independent review and tests for concurrent real HTTP/Octane and role boundary before acceptance.

### P0-03B implementation note — 2026-11-16 (ADR-022 remains PROPOSED)

The P0-03B Laravel proof adds an outermost `DB::transaction` wrapper around fresh user/membership/permission authorization with row locks and PostgreSQL `set_config(..., true)`. Selected company from the session is reauthorized, not trusted. Callback queries must continue to use explicit company filters. Current application database owner/privileges are NOT a durable least-privileged RLS runtime identity. The callback is trusted backend-only code; if it executes arbitrary SQL or a malicious GUC writer, it can still switch the setting before a read. End-of-wrapper checking is only a tamper tripwire, not a preventive database security boundary. The transaction-pool and worker CI probes are development tests, not authorization to activate RLS. Independent review is required, including DB privilege separation, transaction-pooled prepared statement compatibility, worker identity lifetime and race conditions.

## ADR-020 operational elaboration — 2026-11-16 (PROPOSED details; original approval unchanged)

**The six security principles in ADR-020 remain APPROVED. This addition does not approve operational specifications, code, reviewers, merges or production release.** The following implementation-specific documents are prepared for owner decision and independent security inspection:

- [Trusted MFA activation/recovery](security/P0-02_TRUSTED_MFA_ACTIVATION_AND_RECOVERY.md): assign four named roles (identity verifier, second independent approver, authorized issuer, auditor); approve independent verification channels and non-secret evidence/retention; define grant TTL and auditable issuance/reset/rebind. Existing grant row is NOT proof of independent human approval.
- [Company RBAC matrix](security/P0-02_COMPANY_RBAC_MATRIX.md): source-derived mapping (notably `company_manager` own-company `finance.view`), company isolation and strictly separate global `central_management` + `consolidation_viewer` for future report authorization. No financial aggregation is implemented.
- [Key/session/audit operational runbooks](security/P0-02_SECURITY_OPERATIONS_RUNBOOKS.md): custom recovery-code HMAC currently uses `APP_KEY`; key rotation must preserve or consciously reissue digests, **not** simply assume encrypted-cast old-key support. Production origins/timeouts, all-device session invalidation, audit/event retention and owner roles remain undecided.
- [Security review checklist](security/P0-02_SECURITY_REVIEW_CHECKLIST.md): SEC-01..SEC-08 source observations, separate human reviewer, code/findings evidence and independent verdict required.

**Decision gates OPEN:** precise role matrix; names/identity verification/second authorization; auditable recovery and forced-session revocation implementation; key custody/rotation/backups; approved production cookie/sessions/CORS; audit/retention; qualified independent reviewer. No new approved ADR or implicit technical authorization is recorded by these proposals.

---


This reflects explicit business-owner approval to start the ERP V2 rebuild.

| ADR | Decision |
|---|---|
| 001 | Laravel 13, React 19 + TypeScript + Vite, PostgreSQL 18, Redis |
| 002 | Modular Laravel monolith + independently deployed React SPA |
| 003 | Shared PostgreSQL schema, UUIDv7 domain IDs, `company_id` for legal records, composite same-company FKs |
| 004 | Two independent legal entities Egypt and Italy, with management consolidation eliminating intercompany activity |
| 005 | RBAC with company scoped grants; company managers, central management, limited external accountants |
| 006 | First-party Sanctum SPA cookie sessions, CSRF; MFA for privileged accounts; no browser localStorage tokens |
| 007 | Versioned REST `/api/v1`, OpenAPI-derived frontend types |
| 008 | ISO currencies and decimal money, historical snapshot FX and configurable reference/override |
| 009 | Financial records immutable once issued/posted; versioned corrections/reversals and full audit history |
| 010 | Transactional outbox, Redis queues, idempotent billing and financial mutations |
| 011 | Task-timer + manual time entries, PM approval, separate attendance and flexible scheduling |
| 012 | Custom project milestones trigger Finance-reviewed invoice drafts; recurring SaaS/maintenance/hosting invoices are automatic |
| 013 | Tax documents prepared/exported; external accountants file officially outside ERP |
| 014 | Intercompany monthly Egypt-to-Italy service invoice plus project-specific charges |
| 015 | No client portal, asset inventory, automatic bank transaction import or provider API integration initially |
| 016 | Docker + Coolify; staging isolated from production; no V1 cutover until independently approved and reconciled |
| 017 | Tailwind 4, shadcn/ui, TanStack Query/Router, React Hook Form/Zod for eventual frontend design system |
| 018 | Phase 0 foundation then lead-to-cash, execution/HR, SaaS/support, full accounting/payroll/analytics |
| 019 | **Approved 2026-11-16:** GitHub-first cross-chat continuity: one ChatGPT chat per tracked Issue/task, dedicated topic branches and PRs, mandatory status/decision/handoff files and test evidence. See `docs/WORK_PROTOCOL.md`. |
| 020 | **Approved 2026-11-16 (POLICY PRINCIPLES ONLY):** privileged MFA, trusted first activation, audited/two-person lost-device recovery, company RBAC/consolidation isolation, protected key lifecycle, HTTPS/session security. Exact operations/security review/merges and deployments remain separately gated. |
| 021 | **Approved 2026-11-16 (INTEGRATION ONLY):** merge P0-02 PRs #6 backend/#2 frontend to `rebuild/erp-v2`; all SEC-01..08 security work remains open; no real users/deployment. |

## ADR-021 execution record — non-production P0-02 merges (2026-11-16)

Owner-authorized merges were **EXECUTED**: Backend PR #6 squash [`6c2894d`](https://github.com/Elmo-Tech/erp-backend/commit/6c2894d46ec5f48fca8f769010698c29631dff20) and Frontend PR #2 squash [`661ac4a`](https://github.com/Elmo-Tech/erp-frontend/commit/661ac4a01ecfb3f55c712575490f75cf002b160e), both on `rebuild/erp-v2`. **No independent security review was performed, no production or staging deployment authorized.** SEC-01..08 remain open in [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7). Development can continue on [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8) without activating real privileged users.

This execution record supersedes ADR-021's earlier future-tense "merge pending" text but **does not** waive any production gate.

---

## ADR-021 — Owner-directed P0-02 non-production integration acceptance (2026-11-16)

**APPROVED: merge the two specified PRs to development integration ONLY. NOT production/sign-off.**

Owner explicitly authorized integration-only squash merges of Backend PR #6 and Frontend PR #2 into their respective `rebuild/erp-v2` branches, accepting responsibility for non-production project review. This is NOT a formal independent security review, does NOT complete SEC-01..SEC-08, and does NOT authorize real privileged user onboarding, production/staging deployment, `main`/`oberation` changes, key rotation or V1 data migration. Owner approval is for the development integration action only; security production gates remain.

### Exact scope

- Squash merge Backend [PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6) and Frontend [PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2) into `rebuild/erp-v2` after verifying current CI and mergeability. Draft-to-ready transition solely to enable requested merge is permitted.
- No release, deployment, production account configuration or real data; no changes to `main` or `oberation`.
- Owner assumes accountability for integration acceptance but has **not supplied an independent security reviewer**. Do not claim independent review happened.

### Deferred security and release work

SEC-01 HIGH: no enforced dual-person auditable trusted MFA activation grant issuer/verifier/approver; SEC-02 HIGH: no audited lost-device rebind/reset plus full session kill and notifications; SEC-03 HIGH before key rotation: recovery HMAC derived from APP_KEY; SEC-04..08 exact RBAC (including own-company company_manager finance.view), session expiry/revocation, audit integrity, staging-origin checks and concurrency/qualified security review remain OPEN. No production readiness claim. Keep Issue #4 OPEN or transfer each unresolved item to tracked follow-up Issues.

### Verified prior code

Verified prior to docs: Backend CI #43895840430 SUCCESS at `12abef38eb2dd4fa5c5a6dc6ee971c58e7d9827d`; Frontend CI #43890384964 and Chromium #43890384899 SUCCESS at `653adee1c456582e8fc004d006ba62a5a2fce7c9`; Chromium pinned atomic backend `ceded61c936009105b0607c022c02967e2645a6b`. New docs-only commit requires its own CI check.

Actual merge SHAs and post-merge CI results must be recorded in the task handoff/status after the operation; approval by itself is not evidence of a merge.

---

## ADR-020 — Owner-approved P0-02 security policy principles (2026-11-16)

**Status: APPROVED — policy principles ONLY.** The ERP owner explicitly replied "اعتمدتها" to the proposed MFA, recovery, RBAC, cryptography and session-security principles. This is **NOT** approval of a specific operator, exact role-permission matrix, infrastructure/production parameters, completed human security review, PR merge, release, deployment or migration of real accounts.

### Approved requirements

1. **Mandatory MFA:** require TOTP/MFA for `central_management`, `company_manager` (Egypt/Italy), `finance`, `hr`, and `external_accountant` users; no privileged bypass. Once enrolled, MFA cannot silently be disabled to avoid step-up.
2. **Trusted first activation:** password alone cannot register an initial privileged factor. A designated, accountable trusted operator must verify identity and issue a time-limited, single-use, auditable and revocable authorization after independent authorization/approval. No public grant-issuance API.
3. **Account recovery/rebinding:** prioritize valid single-use recovery codes. Lost device with no code requires verified identity, a trusted administrator and a second independent approval, followed by invalidation of old MFA factors/codes/sessions as appropriate, audit entry and user notification. No self-service bypass or undocumented emergency reset.
4. **Egypt / Italy and consolidated RBAC:** separate legal-company membership and financial/payroll permissions. Managers and external accountants remain scoped to their company, with external accountants denied payroll. Global `central_management` may view/select both companies but has **no implied raw finance/payroll access**. Future consolidated-report permission requires an additional explicit `consolidation_viewer` global grant; detailed financial data still needs company-specific authorization. The actual consolidated report computation is outside P0-02.
5. **Encryption key management:** protect encryption keys in approved secret storage, maintain secure recoverable backups, document old-key compatibility, audited/staged rotation/re-encryption, recovery/restore testing and rollback before production. **No APP_KEY change/rotation is authorized by this decision.**
6. **Session/security baseline:** first-party Sanctum cookie sessions, CSRF, HTTPS and Secure/HttpOnly cookie attributes as appropriate, a restricted CORS origin allowlist, explicit server-enforced session expiry/revocation and sensitive-response no-store controls. The browser is never the authorization source of truth.

### Still OPEN — requires detailed specification, implementation or separate approval

- Named operator and independent approver, identity evidence requirements, issuance/revocation UI or operator procedure, audit/retention and incident/lockout runbooks.
- Exact recovery/rebind administrative implementation, approval evidence, factor invalidation and notification channels.
- Precise per-role/grant permission matrix and consolidated report fields/data classes (the granular matrix in `AUTH_SECURITY_CONTRACT.md` is still **PROPOSED** until separately reviewed).
- Key custody architecture, backups/restore drill, rotation schedule, dual-key strategy and monitoring (no production rotation now).
- Exact staging/production hostnames/CORS/cookie domains, SameSite/Secure handling, session idle/absolute timeout, account provisioning and rate-limit lockout response.
- **Independent qualified human security reviewer**, fixes and sign-off of the backend/frontend diffs. Passing CI is evidence, not a human review.
- **Separate explicit owner authorization** for PR merging and, later, for staging/production deployment. Issue #4 remains OPEN and all related PRs remain Draft until gates are closed.

### Evidence/status at time of approval

- Backend atomic code `ceded61c936009105b0607c022c02967e2645a6b` validated by [CI #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049): PostgreSQL 18, 27 PHPUnit tests / 233 assertions, Docker.
- Frontend browser flow and immutable backend pin validated by [Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) and [Frontend CI #43885394399](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394399).
- Backend documentation head before this decision: `df24ef918e535e37e15a42d749cfd894ab847144`; [CI #43885210439](https://github.com/Elmo-Tech/erp-backend/actions/runs/43885210439) PASS.

**Supersession rule:** Previous P0-02 `ALL PROPOSED / NONE APPROVED` passages below are historical drafts. They do **not** override this decision. Their unchecked boxes indicate operational/review work still outstanding, **not** rejection of these approved principles.

---
## Legal / accountant gates not waived
- Egypt and Italy accountants must verify chart of accounts, legal invoice schema, tax codes, FX gains/losses, intercompany reconciliation, payroll, insurance and record retention.
- Official submission and statutory filings are intentionally outside the application initially.
- Annual SaaS/prepaid contract revenue may require deferral; payment != recognized revenue.
- Confirm base currencies, timezone, policy dates and real legal company names before production seed.

## Open implementation decisions
FX rate provider and staleness policy; allowed production/staging origins for SPA auth; object storage and retention; backup RPO/RTO; migration of historic V1 data and accounting opening balances.

## P0-02 earlier implementation proposals — HISTORICAL; see approved ADR-020 (2026-11-16)

The existing approved ADR-005 company-scoped RBAC and ADR-006 first-party Sanctum cookie/MFA remain binding. Implementation details below are **PROPOSED**, not a new approved ADR:
- Granular role/permission matrix and global management limited to company overview until explicit company finance/payroll membership is granted (see `AUTH_SECURITY_CONTRACT.md`).
- First privileged login uses password-verified, five-minute MFA enrollment with TOTP/recovery codes. Production bootstrap/recovery and administrator reset procedure require explicit security-owner signoff before rollout.
- Dev SPA and API origins localhost:5173 and localhost:8000 are isolated CI/test values only. Production origins, domains and cookie settings are still NOT APPROVED.

## P0-02 earlier security-review response — HISTORICAL PROPOSALS (2026-11-16)

These are implementation candidates in draft PR #6/#2 and **not** approved business/security policy. The user must explicitly approve the final decisions before P0-02 is merged:

1. **MFA mandatory roles:** `central_management`, `company_manager`, `finance`, `hr`, `external_accountant`. No privilege-based MFA bypass. Other users who voluntarily enroll continue to require MFA.
2. **Trusted first-time MFA activation:** password alone is **not** enough to register a new factor. `user_mfa_enrollment_authorizations` must have an externally verified, time-limited, unconsumed grant with an internal approval reference. No public grant-issuance API; operator issuance, second-person identity approval, logging, and revocation playbook are **not yet approved or delivered**. Provisioning references are identifiers, not PII or tokens. Challenge lasts five minutes, authorization has its own expiry.
3. **Lost-device / rebind:** prefer remaining single-use recovery code; otherwise authenticated trusted administrator + documented identity verification, second-person approval, recorded change, and revoked prior enrollment. There is **no unaudited or public reset/rebind endpoint**. Exact staff workflow and authentication criteria await explicit user approval.
4. **Central management vs consolidation:** `central_management` permits overview and switching between active companies, but not raw finance/payroll access. An additional `consolidation_viewer` active global grant is **proposed** for authorizing future consolidated *reports only*. Both grants are required; company financial detail still requires explicit same-company membership/role. Actual eliminated intercompany consolidated reports are **NOT IMPLEMENTED** in P0-02 (future M16).
5. **Data protection:** Laravel `encrypted` cast stores TOTP secrets encrypted under the application encryption key; `mfa_secret`, `mfa_last_step` are hidden from model serialization. Temporary enrollment seed is individually `Crypt::encryptString`-encrypted in sessions regardless of `SESSION_ENCRYPT`. `/enroll/start` intentionally returns the seed **once per start** so the user's authenticator can be configured; no-store response required. Recovery codes are HMAC-digested at rest and shown once on confirmation. **Key rotation** requires an approved backup/restore and dual-key compatibility plan with a staged re-encryption and fail-safe rollback; changing `APP_KEY` without this could make existing MFA secrets unrecoverable. Do not rotate production keys as part of this PR.
6. **Brute-force controls:** login per-IP (20/5 min), per-account and per IP/account pair (5/5 min). MFA invalid attempt per-account and per-session (5/5 min), per-IP (30/5 min), with start-enrollment throttled at 3/5 min. Cache-store persistence and operational response to intentionally locked accounts need a production review.

This historical appendix predates ADR-020. Its underlying high-level principles were subsequently approved in ADR-020; detailed operational choices still require review. No real-user enrollment or production rollout is authorized.

## P0-02 remaining operational approval checklist — PRINCIPLES APPROVED in ADR-020; DETAILS OPEN (2026-11-16)

**ADR-020 grants owner approval to the six security policy principles only.** Unchecked tasks below represent precise policy procedures, thresholds, ownership, evidence or deployment settings awaiting specification and independent review; do not interpret them as unapproved principles. Previous code defaults are not automatically production-approved:

- [ ] **Mandatory privileged MFA:** require TOTP/recovery for central management, Egypt/Italy company managers, finance, HR and external accountants; determine whether any additional staff roles or temporary step-up exceptions need coverage and whether optional MFA stays enforceable once enrolled.
- [ ] **Trusted initial activation:** identify named accountable operator(s), an independent second approver and proof-of-identity method; confirm 10-minute authorization TTL vs five-minute password challenge, approval reference/audit retention, notification, revoke/reissue pathway and emergency lockout response. The current code has **no public issuance API** and does not implement human verification.
- [ ] **Lost-device recovery/rebind:** define single-use recovery-code-first procedure, multi-person identity verification for administrative reset, invalidation of previous factors/sessions/codes, immutable audit log/notification and denial of unapproved emergency bypass. No admin reset route exists in P0-02.
- [ ] **Egypt/Italy legal-entity RBAC:** approve role-permission mapping and company manager scope; external accountant own-company finance/export **but no payroll**; central management both-company overview **without** implicit raw financial details. Explicitly decide whether `consolidation_viewer` + `central_management` gates future consolidated reports and what data classifications the report may expose. Actual consolidated reporting belongs to M16, not P0-02.
- [ ] **Operations/cryptography:** approve APP_KEY secure key storage, backup/restore, old-key compatibility and staged re-encryption/rotation, logging metadata/retention/redaction, rate-limit lockout/unlock procedure and recovery-code handling/storage.
- [ ] **Sessions/environments:** approve real staging/production SPA/API origins, HTTPS Secure/SameSite/domain settings, idle/absolute session lifetime, clock skew and production account provisioning/revocation. CI localhost origins do not authorize a deployment.
- [ ] **Independent sign-off and release separation:** assign a qualified human security reviewer different from the implementer to inspect transaction/race behavior, secret handling, browser pin and role isolation. Resolve findings before marking Ready; obtain separate **explicit owner permission for merge**, then separate deployment permission. No authorization implied by this checklist.

**Atomicity implementation is engineering remediation, not an owner security policy approval.** PostgreSQL #43881203049 PASS at `ceded61c936009105b0607c022c02967e2645a6b` (27/233, including rollback/retry injection); Chromium #43876191068 pinned previous `0d2843c` (historical only); the newer [Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) has successfully tested atomic code `ceded61c` with an immutable pin. ADR-020 is now approved for *principles*; keep PRs Draft until independent review and separate merge approval.
