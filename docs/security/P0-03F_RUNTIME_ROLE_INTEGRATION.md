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
- New actual pooled HTTP login, first MFA enrollment, TOTP/recovery replay, logout, bilateral EG/IT isolation, and 5-job reused-worker steps are registered in .github/workflows/ci.yml; final aggregate success has **NOT BEEN VERIFIED** as of this entry. Existing rollback/legacy revocation tests are not a substitute for new-role race proof.

**Next exact step:** verify latest exact-head CI, fix first failing real split-role step without broad grants, add explicit new-role concurrent revocation and rollback negatives, request independent qualified human review. PR #10 remains Draft, Issues #8 and #7 OPEN; no merge, deployment or production/main/oberation changes.
