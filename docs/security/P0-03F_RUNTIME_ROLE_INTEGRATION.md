## P0-03F final evidence / P0-03G command-boundary review (2026-11-17)

**CONFIRMED engineering PASS, NOT security release approval.** The task branch pre-handoff HEAD `93310fd8a302b186a895c744dcf99ae7b1862d87` completed [Backend CI #44069798799](https://github.com/Elmo-Tech/erp-backend/actions/runs/44069798799) with **SUCCESS on 38/38 workflow steps** including PostgreSQL 18/PHPUnit/migrations/rollback, real transaction-pooled PgBouncer, restricted Laravel Web/MFA/Session/Queue SQL principals, first MFA enrollment/TOTP/recovery one-time/Login/Logout, Egypt ↔ Italy authorization, concurrent membership revocation, five queue jobs on a reused Laravel worker, synthetic MFA Recovery INSERT failure/atomic rollback, Docker build and API health. The immediately preceding runtime-code HEAD `dbc0f7efac700a3fabb11d270df14977481c6b98` passed [CI #44069174715](https://github.com/Elmo-Tech/erp-backend/actions/runs/44069174715). A GitHub commit comparison confirmed that `dbc0f7e..93310fd` modified only `docs/DECISIONS.md`, `docs/PROJECT_STATUS.md`, `docs/handoffs/P0-03.md` and `docs/security/P0-03F_RUNTIME_ROLE_INTEGRATION.md` — **no runtime changes after the tested code**. This new handoff docs commit must obtain its own exact-SHA CI; earlier runs do not automatically validate it.

**Still OPEN / NOT SECURITY SIGNED OFF:** split SQL connections all live inside the same Web PHP process; that process can access MFA credentials and decrypt factors if fully compromised. `WebUser` intentionally reads password hashes and is vulnerable to their exposure by arbitrary Web SQL. The CI-only `public.p003f_lock_authorization_scope(bigint,uuid)` SECURITY DEFINER helper uses fixed SQL and qualified tables, but accepts caller IDs under `EXECUTE` by the Web principal; function privileges, ownership/search_path and intentional lock/availability abuse require independent qualified review. A custom writable `app.company_id` GUC is **NOT** a trusted DB tenant identity and RLS remains **OFF**. SEC-01..08 account activation/reset/key/session/security operations remain [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7) OPEN; [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8) OPEN; [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10) OPEN/UNMERGED with **no recorded formal human review**. ADR-022 is still **PROPOSED** (engineering recommendation: DEFER permanent RLS / Trusted Issuer). Octane, nonempty production-like upgrade, production privilege configuration and human security acceptance NOT VERIFIED.

**P0-03G next action — DESIGN / TEST FIRST:** independently threat-model Web-process compromise vs SQL-only injection; define whether a separate MFA command service or isolating identity process is warranted. Its factor keys/DB credentials must not be accessible to Web, commands must be specifically authorized and bound to user-owned challenges, rate limited/idempotent/audited, and sensitive recovery/enrollment/reset and compromised/replayed service credentials fail closed. Evaluate real deployment/ops cost; **a broker trusting Web-supplied user_id alone is not a security boundary**. Then review the SECURITY DEFINER lock helper and complete cross-role grants. Implement a smallest safe spike only on task branches/CI with synthetic identities and exact-head proof. If a strong process boundary is not feasible, state residual risk and track it as a pre-release gate rather than calling it solved or building an untrusted signer. Keep production release separate from any future non-production merge authorization.

**NO MERGE / DEPLOY / PERMANENT RLS / REAL USERS / KEY ROTATION / main / oberation actions authorized.** Historic snapshots below remain for audit; this status applies to the stated pre-handoff SHA, not future changes.

---

## P0-03F code-HEAD integration verification — 2026-11-17

**ENGINEERING PASS:** full [CI #44069174715](https://github.com/Elmo-Tech/erp-backend/actions/runs/44069174715) at exact code `dbc0f7efac700a3fabb11d270df14977481c6b98`. PostgreSQL 18/PgBouncer, real restricted Web/MFA/Session/Queue Laravel HTTP+worker, concurrent revocation, and synthetic restricted MFA failure-injection rollback all passed. Fault-injection scope: trigger deliberately fails recovery INSERT only for a synthetic disposable user; test asserts the consumed grant, encrypted factor, last TOTP step, and recovery hashes were all rolled back, and checks for the expected synthetic trigger error. Role negative probes and prior migrations/rollback, PHPUnit, Docker smoke passed. **This is NOT security signoff; same PHP process secret exposure, SQL helper review, and SEC-01..08 remain open.** A later docs-only commit must receive a distinct CI result.

---

# P0-03F — Laravel Runtime Roles: Query/Grant and Threat Inventory

**2026-11-17. IN PROGRESS / NOT SECURITY SIGN-OFF.** Draft PR #10 and Issue #8. Issue #7 SEC-01..08 remains OPEN; ADR-022 PROPOSED (defer permanent RLS, do not build a Trusted Issuer now). Only synthetic users, disposable PostgreSQL 18, PgBouncer, and CI-only grants. No actual production role grants or credentials.

## Exact SQL/read/write inventory

| Caller / path | Required SQL | Runtime SQL principal | Denied |
| --- | --- | --- | --- |
| Web login, auth user provider, pendingUser, /auth/me | SELECT users(id,name,email,password,is_active), by email/id; Hash::check in PHP | p003e_web | mfa_secret, mfa_last_step, remember_token, full users SELECT, UPDATE users |
| Company RBAC, MFA requirement, /companies/switch, /companies/authorized-read | SELECT companies, company_memberships, company_role_assignments, global_role_assignments; explicit company predicates and live active membership/role | p003e_web | membership/role UPDATE or finance/payroll raw data |
| AuthenticatedDatabaseContext in partition mode | outermost PostgreSQL transaction; EXECUTE p003f_lock_authorization_scope(bigint,uuid), recheck membership/permissions, SET LOCAL app.company_id; transaction rollback | p003e_web | UPDATE(updated_at), owner roles, finance |
| MFA factor status / verify | SELECT users(id,is_active,mfa_secret,mfa_last_step); decrypt encrypted factor only on MFA connection; conditional UPDATE mfa_last_step | p003e_mfa | password hash, roles, session and queue access |
| MFA first enrollment | SELECT authorizations; conditional UPDATE consumed_at; conditional UPDATE users(mfa_secret,mfa_last_step); DELETE + INSERT 8 recovery hashes in ONE transaction on MFA connection | p003e_mfa | issue/insert trusted grants or edit approval metadata |
| MFA one-time recovery | DELETE recovery code by user_id + code_hash; success only if affected=1 | p003e_mfa | Web recovery read/write |
| Laravel cookie session, login/logout/CSRF | SELECT/INSERT/UPDATE/DELETE sessions on SESSION_CONNECTION=pgsql_session | p003e_session | users/MFA/queue/finance |
| Queue and failed jobs | SELECT/INSERT/UPDATE/DELETE jobs/failed_jobs; sequence USAGE via DB_QUEUE_CONNECTION=pgsql_queue and DB_FAILED_CONNECTION=pgsql_queue | p003e_queue | users/MFA/session/finance; domain work still needs separately authorized Web DB read |

**Scope of opt-in configuration:** ERP_ROLE_PARTITION_REQUIRED=true, ERP_MFA_DB_CONNECTION=pgsql_mfa, SESSION_DRIVER=database, SESSION_CONNECTION=pgsql_session, DB_QUEUE_CONNECTION=pgsql_queue, DB_FAILED_CONNECTION=pgsql_queue. Distinct DB_MFA_*, DB_SESSION_* and DB_QUEUE_* credentials, hosts, ports; *_PGBOUNCER_TRANSACTION=true selects compatible PDO emulated prepares for transaction pooling. These are CI-only settings, not a production configuration or protected credential-distribution design.

**Unexpected SQL detail:** Laravel Query Builder exists() produces SELECT EXISTS(SELECT * ...), rejected for column-scoped users grants. hasFactor() must explicitly select permitted id rather than SELECT *. WebUser has a global explicit select and maps to users table. PostgreSQL FOR SHARE, like FOR UPDATE, needs UPDATE privilege; merely switching locks cannot remove timestamp grants. The CI-only SECURITY DEFINER p003f_lock_authorization_scope locks active user/company/membership and roles with fixed SQL, search_path pg_catalog/pg_temp, grants EXECUTE only to Web, then Laravel revalidates specific permission and applies explicit legal-company predicates.

## Security boundaries and unresolved HIGH risk

- Separate connections **inside the SAME PHP process** do not prevent compromised Web PHP from reading or using MFA/Session/Queue credentials or decrypted factor secrets. This is NOT an isolated MFA command broker, not trusted activation SEC-01, not SEC-02 verified reset/rebind, and not an accepted production design. Require a separately authenticated/authorized process/service with isolated credential custody, command limits and audit before signoff.
- Web role may read password hashes; compromise can leak them. MFA principal can update factors if its SQL credentials are compromised. Queue job payload/session access require separate process ownership and integrity review.
- SECURITY DEFINER helper still accepts caller IDs and can be invoked by arbitrary Web SQL, creating potential locking/DoS; human review required of code, function owner, EXECUTE ACL and all search_path resolution.
- Arbitrary set_config('app.company_id', ...) spoofs GUC-only RLS. NO permanent RLS, NO Trusted Issuer, NO claim that pooled GUC is authorization.
- SEC-01..08, APP_KEY/recovery-HMAC rotation, trusted grant issuance, independent review, Octane, production-like nonempty upgrade and production credentials remain NOT VERIFIED.

## CI evidence as of this entry

- Baseline P0-03E docs-head CI #44044766275 SUCCESS on ed90ca6, distinct from new implementation.
- P0-03F intermediate failures: #44066321009 and #44066641984 (PHP syntax), #44066982810 (WebUser table mapping), #44067293836 (split-role exists() denied by PostgreSQL), #44067852530 (test GET vs POST enrollment start); corrected in subsequent commits.
- The listed pooled Auth/MFA/Session and 5-job reused-worker steps are **PASS on exact code d7b3114** under [CI #44068168985](https://github.com/Elmo-Tech/erp-backend/actions/runs/44068168985), including old migration rollback and Docker. Later split-role concurrent revocation test e8f7201 is NOT VERIFIED until its own CI finishes. Existing rollback/legacy revocation tests are not a substitute for new-role race proof.

**Next exact step:** verify latest exact-head CI, fix first failing real split-role step without broad grants, check the newly added split-role revocation/concurrency test on e8f7201 and finish negative rollback coverage, request independent qualified human review. PR #10 remains Draft, Issues #8 and #7 OPEN; no merge, deployment or production/main/oberation changes.
