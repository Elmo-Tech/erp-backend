# P0-04-ACL — Runtime Database Principal Isolation (NON-PRODUCTION PoC)

## 2026-11-19 — CI #44497530967 verified; narrowly scoped diagnostic and human reviewer package

**Live GitHub confirmation:** Draft PR #22 / Issue #21 OPEN; at intake PR HEAD `60cfcec55306459ee83db4268c0c53801c70868c`, unchanged integration `21a085f5b101126cb6f535fdb03f204cb99d0405`. [Actions #44497530967](https://github.com/Elmo-Tech/erp-backend/actions/runs/44497530967) **COMPLETED / FAILURE** at the exact intake SHA. Every prior workflow step including restricted Laravel/PgBouncer/Compose/Docker succeeded; final cross-user MFA security step failed with the synthetic unauthorized replay-state update, rollback and an unrelated PHP `Array to string conversion` warning. Review submissions and inline threads: **none**.

**Bounded changes in this handoff update:** correct only `(string)$target` to `(string)$target['id']` in `tests/integration/p004_mfa_cross_user_negative.php`; preserve fail-on-vulnerability and ROLLBACK. Add [Security Threat Model and Human Reviewer Package](../security/P0-04-ACL_THREAT_MODEL_REVIEWER_PACKAGE.md), refine [Security Gate](../security/P0-04-ACL_SECURITY_REVIEW_GATE.md) and project status. These are review documents, **not an approved security remediation**, new broker, authentication rewrite, or human approval. CI on the **new documentation/test HEAD is NOT VERIFIED at commit time**; record actual run after it appears.

**Impact:** demonstrated arbitrary-target `mfa_last_step` replay-state modification under real `elmo_runtime` within a disposable transaction, with possible repeated challenge availability impairment. No Laravel login bypass is demonstrated. Recovery requires matching digest knowledge and enrollment an existing valid grant; neither has an independent caller-to-user proof. Web `APP_KEY` and session authority remain same-process. Consult the linked review package for A/B/C alternatives and conditional risks.

**Review/decision:** **NO-GO development merge, production, deploy, financial posting and real data. GO only to independent human review preparation.** No GitHub Review Requests without owner-provided usernames. DBA, MFA/session security and financial-controls reviews are **NOT RECORDED**; independent issuer/verifier contract **NOT APPROVED**. Keep #7/#8/#11 OPEN, ADR-024 PROPOSED, #14 UNMERGED, #20 NEVER MERGE, PR #22 DRAFT; no `main`, `oberation` or integration edit.

**One precise next action:** inspect the exact-head CI of this diagnostic/docs commit (expected security negative FAILURE while vulnerable), then ask the owner for three reviewer GitHub usernames to review and approve a separately enforced identity/MFA-assurance contract before any functional fix.

## 2026-11-19 — Focused AI security gate: NO-GO for merge, presentable for independent human review

Original source/CI at `4552a964a6ac6e3e79b221d528be9734034dd286`: [run #44386318271](https://github.com/Elmo-Tech/erp-backend/actions/runs/44386318271) SUCCESS. [Detailed PASS/FAIL/NOT VERIFIED report](../security/P0-04-ACL_SECURITY_REVIEW_GATE.md). CRITICAL: `p004_consume_mfa_step` trusts `elmo_runtime`, caller-supplied target and an unproved time-step; no target identity/TOTP checked in DB. Separate functions `p004_consume_recovery` and `p004_enroll_mfa` also have missing independently bound identity. Web can read password hashes and encrypted MFA fields plus has APP_KEY; Worker can DML Journal rows and mutate period timestamp, while P0-04A remains unmerged. SECURITY DEFINER NOLOGIN/qualified-name and effective grant negatives are genuine but insufficient.

Added a **non-production, synthetic, rolled-back cross-user negative regression test** and an end-of-workflow blocking security check. If cross-user update succeeds, the test prints **SECURITY FAIL** and exits nonzero: **not a PASS of protection**. The resulting amended-head CI is **NOT VERIFIED at time of committing this handoff**; inspect its actual outcome/commit before reporting. No functional MFA rewrite: there is no independently trusted DB proof issuer or agreed design, and a same-process APP_KEY-based claim would not be sufficient.

**Decision:** GO to show blocking evidence to owner-selected qualified independent DBA, application security/MFA and accounting safeguards reviewers; **NO-GO** to development merge, production deployment and real data. Keep PR #22 Draft and Issue #21 open, ADR-024 PROPOSED, security #7/#8/#11 open, PR #20 NEVER MERGE. No human review or owner merge approval implied.

**One exact next action:** Verify the new exact-head GitHub Actions run and have the project owner nominate independent qualified reviewers to design an independently enforced caller-to-user MFA assurance boundary (plus Worker posted-entry/audit gates). After tested fixes and human sign-off, obtain a **separate explicit owner merge command**. No new branch/issue or integration changes.

---

## 2026-11-18 — Final code CI checkpoint and handoff

**Exact hardened-code CI evidence (2026-11-18):** commit `0a7ee22f489bce8a9727dbec5d10e08a765f960e`, GitHub Actions [#44385647946](https://github.com/Elmo-Tech/erp-backend/actions/runs/44385647946) **COMPLETED SUCCESS**. Verified PostgreSQL 18 owner migrations then independent Web/Worker SCRAM LOGIN, Web-negative object grants, Worker synthetic EG/IT draft CRUD and FK checks, Auth/MFA enrollment/recovery and database Session/Queue/Company CRUD under real `elmo_runtime` both direct and through PgBouncer transaction pooling, runtime migration denial, synthetic Compose PG owner provisioning with nonroot UID 10001 Web and Worker, Docker image build and API health. This documentation-only update creates a later SHA; **the code CI cannot be attributed to that newer documentation SHA** until its own workflow completes.

**Current engineering outcome:** Source and code tests support object-level ACL design with strong **limitations** in [privilege matrix](../security/P0-04-ACL_PRIVILEGE_MATRIX.md). The DB function owner is NOLOGIN, but its SECURITY DEFINER functions can be invoked from a compromised Web process. Worker gets column-only UPDATE on accounting_periods.updated_at due P0-03 trigger locking and has no access to user/MFA/audit reads. `tests/Feature/RestrictedRuntimeTest.php` covers direct/PgBouncer Laravel auth, MFA enrollment + single-use recovery, DB sessions, queue enqueue and company checks; it is not a full queue-consumption or human-identity verification proof.

**Credential plan:** See matrix runbook for distinct Web/Worker secret provisioning, rotation and fail-closed revoke/reconnect/rollback. No real operational drill was executed.

**One precise next action:** independently inspect the three SECURITY DEFINER procedures, function/PUBLIC/extension privilege boundary, grant issuer trust model, Web process takeover and posted-row write policy; record review findings. Do not mark ready or merge until explicit separate owner authorization. Code status remains IN PROGRESS rather than VERIFIED due outstanding human gate.

## CI failures found and follow-up — 2026-11-18

- [CI #44384002103](https://github.com/Elmo-Tech/erp-backend/actions/runs/44384002103) FAILED on early commit `7a335bc` because the worker password was not yet supplied to the new psql SQL variable; corrected in later workflow change.
- [CI #44384803179](https://github.com/Elmo-Tech/erp-backend/actions/runs/44384803179) FAILED on `43411992` at Worker draft CRUD: P0-03 `SELECT ... FOR SHARE` needs column-level UPDATE on accounting_periods. Added strictly `UPDATE(updated_at)` for Worker and negative privilege checks denying period state, dates, company and locks. This is a reviewable residual metadata-write weakness.
- **Superseded by successful hardened-code CI #44385647946** on 0a7ee22 above. Earlier failures remain part of the audit trail.

## 2026-11-18 — Source-audited follow-up (NEW CODE / CI NOT VERIFIED)

Live GitHub before edits: task branch HEAD `b097db0188b8846377903bc63a6d1b97615a1b26`; [baseline CI #44376461374](https://github.com/Elmo-Tech/erp-backend/actions/runs/44376461374) completed SUCCESS; integration remained `21a085f5b101126cb6f535fdb03f204cb99d0405`. PR #22 Draft/open, Issue #21 open. No newer work found at task intake.

New commits after baseline introduce object-level rights, NOLOGIN MFA executor with parameterized scoped functions, non-root image, separate Worker LOGIN/Compose service, isolated Compose CI override, owner-seeded restricted Laravel suite, direct/PgBouncer permission probes, and this [object-level matrix + lifecycle runbook](../security/P0-04-ACL_PRIVILEGE_MATRIX.md). New files include `tests/Feature/RestrictedRuntimeTest.php`, `tests/integration/p004_laravel_fixture.php`, `compose.ci.yaml`, `docs/security/P0-04-ACL_PRIVILEGE_MATRIX.md`; existing `database/security/p004_acl_disposable.sql`, `app/Domain/Auth/MfaService.php`, `app/Http/Controllers/AuthController.php`, `tests/integration/p004_acl_runtime.php`, `compose.yaml`, `Dockerfile`, `.github/workflows/ci.yml` changed.

**Exact-head evidence:** NOT VERIFIED for this new source. Baseline successful CI predates changes. No local Composer/PostgreSQL/Docker execution was possible. Ensure runner actually executes full workflow, inspect every job and match run `head_sha` with *final* branch HEAD before any claim of success.

**Outstanding:** qualified human security review, MFA function threat model and grant provenance, Web-process compromise, read access to password/MFA ciphertext, worker ability to mutate posted rows in unmerged P0-04A, full operational credential rotation drill, trusted audit/outbox redesign, per-company DB authorization and full queue consumption proof. No integration merge, no PR #20 code, no deployment, no permanent RLS. ADR-024 remains PROPOSED; security issues #7/#8/#11 remain OPEN.

**One precise next action:** obtain an exact-HEAD GitHub Actions run, resolve any SQL/PHP/Compose failures, update this file and status with traceable run/commit results; then independent human reviewer and separate owner merge approval.

---


## Exact-code CI evidence — 2026-11-18

**VERIFIED first complete candidate:** [GitHub Actions #44375723500](https://github.com/Elmo-Tech/erp-backend/actions/runs/44375723500) COMPLETED SUCCESS on exact commit 9da30bdbfeeda5591daa74547b8f069de08f1f42. Its real PostgreSQL 18 LOGIN role provision and direct role probe SUCCESS, independent PgBouncer SCRAM startup and restricted runtime probe SUCCESS, synthetic cleanup SUCCESS; pre-existing PG18 migrations, PHPUnit, P0-03 populated upgrade/rollback, two-client concurrency, real PgBouncer Auth/MFA/Session/Queue, Docker build and API health SUCCESS. Journal immutable-state proof is intentionally NOT VERIFIED because Draft PR #14 guards are absent on this independent integration-based branch. This *documentation update* creates a NEW HEAD and must get separate exact-head CI before claiming its final validation.

**Date:** 2026-11-18. **Issue:** https://github.com/Elmo-Tech/erp-backend/issues/21 ; parent #13. **Branch:** task/p0-04-acl-runtime-principal, from unchanged integration 21a085f5b101126cb6f535fdb03f204cb99d0405. **Status:** IN PROGRESS; NO MERGE/DEPLOY. **CI:** NOT VERIFIED until exact PR HEAD completes.

## Discovery
Original compose.yaml exposed the entire .env (including owner credential) to API and used DB_USERNAME=elmo with POSTGRES_USER=elmo. That initial PostgreSQL role is superuser; it can bypass journal guards via TRUNCATE, ownership, trigger-disable and DDL. Existing PgBouncer CI uses auth_type=trust and privileged migration owner. config/database.php uses DB_* and an optional pooled PDO setting, not independent DB authorization.

## Scope and implementation
Compose now injects explicit API variables instead of env_file .env, requires distinct RUNTIME_DB_PASSWORD and DB_USERNAME=elmo_runtime; owner POSTGRES_PASSWORD remains confined to PostgreSQL bootstrap and one-off migrations. Compose now fails closed if runtime password/APP_KEY is missing. IMPORTANT: A human operator must provision a matching restricted LOGIN on the *local* database before starting the API. The PoC SQL refuses anything but the disposable elmo_erp_test database and owner identity; it is NOT a general production role installer. Never run migrations as runtime. Changing POSTGRES_USER on an existing volume does not migrate or rotate preexisting DB identities.

The disposable SQL provisions a fresh password LOGIN elmo_runtime with NOSUPERUSER, NOCREATEDB, NOCREATEROLE, NOINHERIT, NOREPLICATION, NOBYPASSRLS, pinned SCRAM password verifier, no owner membership, no public schema/database CREATE or database TEMP; grants CONNECT, schema USAGE, pre-existing public table SELECT/INSERT/UPDATE/DELETE, existing sequence usage, and no TRUNCATE/TRIGGER/REFERENCES/DDL. Future migrations require reviewed grant refresh (no default grants).

The PHP synthetic probe authenticates by a distinct PDO connection with elmo_runtime password (NO SET ROLE), verifies current_user/session_user, privilege metadata, negative SQLSTATE 42501 for TRUNCATE, trigger-disable, ALTER/DROP/CREATE, TEMP and impersonation, allows Egypt/Italy draft DML, and denies cross-company composite-FK changes with 23503. CI runs direct PostgreSQL 18 and separate PgBouncer transaction pooling with auth_type=scram-sha-256 (no trust), then owner cleanup. All existing integrated PHPUnit, P0-03 rollback/upgrade, Auth/MFA, Docker health remain in same workflow.

## Trust limitations — explicitly NOT VERIFIED
- P0-04A guards in Draft PR #14 (HEAD 6e02b4b) are NOT integrated. The role's table UPDATE privileges cannot enforce immutability of already-posted journals on their own. The probe prints NOT VERIFIED without guards; no false positive. DO NOT COPY PR #14/#16/#18 or #20 into this branch. #20 NEVER MERGE.
- Same-company composite FKs do not enforce identity/company authorization. A compromised Web worker with this runtime credential can still write *both companies'* tables; further DB policy, scoped commands, audit and trusted independent session/MFA enforcement are needed.
- PUBLIC EXECUTE on functions, broad table DML, non-PostgreSQL resources, service UID/secret confinement, privilege grants after future migrations, queue/worker process identities, real production data, operational credential rotation, availability and production rollout are not proven safe.
- Human independent qualified DB/security/accounting review NOT RUN; code CI cannot substitute for it. No finance posting API, no production/staging/real financial data; no merge, no permanent RLS.
- ADR-024 stays PROPOSED. Security #7/#8/#11 OPEN; parent #13 OPEN.

## Changed files
database/security/p004_acl_disposable.sql; tests/integration/p004_acl_runtime.php; .github/workflows/ci.yml; compose.yaml; .env.example; docs/handoffs/P0-04-ACL.md; docs/PROJECT_STATUS.md; docs/MODULE_ROADMAP.md.

## Next precise action
Verify complete CI at final exact PR HEAD (not only earlier code commit); repair failures if any. Request independent DB/security review of object grants, PUBLIC functions, PgBouncer SCRAM/connection identity, Compose owner-secret isolation and rollback/rotation plan, then explicit owner permission before any development merge.