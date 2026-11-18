# P0-04-ACL — Runtime Database Principal Isolation (NON-PRODUCTION PoC)

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