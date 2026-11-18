# P0-04-ACL — Object privilege matrix and operator lifecycle (PROPOSED / NON-PRODUCTION)

Date: 2026-11-18. Scope: independent synthetic PostgreSQL 18, Web `elmo_runtime`, Worker `elmo_worker`, migration owner `elmo`, dedicated NOLOGIN `elmo_mfa_executor`. Related Issue #21, Draft PR #22. **This is a proposed database boundary, not ADR-024 approval, production entitlement or independent human sign-off.**

## Source-derived permission matrix

`R` SELECT; `I/U/D` INSERT/UPDATE/DELETE; `E` EXECUTE narrowly scoped function only; `—` no grant. PostgreSQL object privileges do not substitute for server-side company authorization.

| Object class | Web LOGIN | Worker LOGIN | Migration owner | Trusted operator / MFA executor |
| --- | --- | --- | --- | --- |
| `users` (incl. password/MFA ciphertext reads) | R (CURRENT LIMITATION) | R | owner DDL/DML | MFA executor SELECT and UPDATE only `mfa_secret,mfa_last_step,updated_at` |
| `global_role_assignments`, memberships, company roles | R | R | owner DDL/DML | operator changes grants outside Web/Worker |
| `companies` | R/I/U/D (synthetic proof; no public CRUD API) | R | owner DDL/DML | no separate runtime grant |
| `currencies` | R | R | owner DDL/DML | no runtime grant |
| `sessions`, `cache`, `cache_locks` | R/I/U/D | — | owner DDL/DML | no runtime grant |
| `jobs` | R/I (enqueue) | R/I/U/D | owner DDL/DML | no runtime grant |
| `job_batches`, `failed_jobs` | — | R/I/U/D | owner DDL/DML | no runtime grant |
| `fiscal_years`, `accounting_periods`, `ledger_accounts` | — | R; `accounting_periods.updated_at` UPDATE only to allow P0-03 trigger `FOR SHARE` | owner DDL/DML | no runtime grant |
| `journal_entries`, `journal_lines` | — | R/I/U/D (SYNTHETIC DRAFT ONLY; posted immutability NOT PROVEN) | owner DDL/DML | no runtime grant |
| `user_mfa_enrollment_authorizations` | R only, no grant issuance | — | owner DDL/DML | MFA executor SELECT and UPDATE `consumed_at,updated_at` only |
| `user_mfa_recovery_codes` | no raw SELECT/DML | — | owner DDL/DML | MFA executor R/I/D |
| `audit_logs`, `outbox_events`, `idempotency_requests` | — | — | owner DDL/DML | independently designed trusted audit/outbox writer still NOT IMPLEMENTED |
| `migrations`, PostgreSQL DDL, TRUNCATE, TRIGGER, REFERENCES, TEMP | — | — | owner as required | — |
| `p004_consume_mfa_step`, `p004_consume_recovery`, `p004_enroll_mfa` | E only | — | owner administration | functions SECURITY DEFINER owned by NOLOGIN MFA executor |
| Existing `p003_*` functions | no direct E | no direct E | ownership/administration | no unreviewed `PUBLIC EXECUTE` |

The Worker timestamp-only UPDATE exception on `accounting_periods` was discovered from CI #44384803179: the existing P0-03 trigger uses `SELECT ... FOR SHARE`, which needs at least one UPDATE-able column. This allows timestamp-only metadata mutation and is NOT an ideal final boundary; explicit negative column privilege probes guard financial fields. A separately reviewed guard implementation could remove this exception. All Web/Worker GRANTs are object-specific. No `ALL TABLES`, no `ALL SEQUENCES`, no default future grants; no role memberships, superuser, DDL or owner impersonation. `PUBLIC` function EXECUTE is revoked on preexisting public-schema functions. Audit any inherited/public permissions and object ownership on the *actual* target schema; this synthetic script refuses existing principals and cannot be used as a real-system migration.

## MFA trust boundary

The three `SECURITY DEFINER` functions use `SET search_path = pg_catalog, pg_temp`, schema-qualified objects, input shape checks and a NOLOGIN non-superuser owner. They do not grant Web raw write to MFA tables or grant-issuance authority. `p004_enroll_mfa` consumes a pre-existing, unexpired trusted operator authorization and writes factor/recovery data within the same transaction.

**Residual risks / hard stop:** the Web application possesses APP_KEY, the existing users SELECT includes password hashes and encrypted MFA secrets, and a compromised Web process can invoke granted MFA functions. The function does not independently validate TOTP, proof-of-identity, user-to-request binding, or grant provenance. Neither SQL grants nor this PoC establish resistance to Web process takeover, independent trusted recovery, or immutable audit. This boundary needs independent cryptographic/database review and redesigned separation before production.

`elmo_worker` can still update posted journal records if posted rows exist; P0-04A guards are not on integration. No financial posting API is enabled. Synthetic tests are not evidence of per-company SQL isolation. Current database-backed queue mode assigns the Web only enqueue permissions; worker owns dequeue. Redis queue uses a separate worker process, but shared APP_KEY and Redis access remain unreviewed.

## Audit commands (run as authorized operator on synthetic environment)

```sql
SELECT rolname,rolcanlogin,rolsuper,rolcreatedb,rolcreaterole,rolinherit,rolbypassrls
  FROM pg_roles WHERE rolname IN ('elmo','elmo_runtime','elmo_worker','elmo_mfa_executor');
SELECT member::regrole,roleid::regrole FROM pg_auth_members
  WHERE member IN (SELECT oid FROM pg_roles WHERE rolname IN ('elmo_runtime','elmo_worker','elmo_mfa_executor'));
SELECT n.nspname,c.relname,c.relkind,c.relacl
  FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
 WHERE n.nspname='public' ORDER BY c.relname;
SELECT n.nspname,p.proname,p.prosecdef,p.proowner::regrole,p.proacl
  FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
 WHERE n.nspname='public' ORDER BY p.proname;
SELECT defaclrole::regrole,defaclnamespace::regnamespace,defaclobjtype,defaclacl FROM pg_default_acl;
```

Review `information_schema.role_table_grants`, `has_table_privilege`, `has_sequence_privilege`, `has_function_privilege`, `pg_get_functiondef`, extension ownership, trigger definitions, SECURITY DEFINER exposure, effective grants via PUBLIC and all role memberships. This is source review guidance; live results are NOT VERIFIED until exact-head CI and human review.

## Synthetic credential provisioning, rotation, revoke and rollback

1. **Provision:** trusted owner migrates the empty synthetic DB and provisions runtime roles afterward with disposable generated passwords via `psql -v runtime_password=... -v worker_password=...`. Store values in separate secret stores/CI masks, not repo, logs, shared Web env or the Docker image. Confirm `session_user` through direct PG18 and PgBouncer SCRAM. Provisioning refuses non-test databases or existing role names.
2. **Rotate:** independently generate new high-entropy Web and Worker secrets; protect old secrets under restricted operator custody for an explicitly bounded rollback window; use an operator-controlled privileged SQL `ALTER ROLE elmo_runtime PASSWORD <new-secret>` and corresponding `elmo_worker` rotation (never paste secrets in SQL/logs); update independent Web and Worker secret references, reload/recreate clients and PgBouncer auth material, drain old connections; verify only new logins succeed and reconcile pool/backend identity. Cross-check session termination and rollback before any real deployment.
3. **Emergency revoke:** stop API/Worker ingress; as authorized owner issue `ALTER ROLE elmo_runtime NOLOGIN` and/or `ALTER ROLE elmo_worker NOLOGIN`, `REVOKE CONNECT ON DATABASE ...`, remove scoped function EXECUTE if needed, and terminate active sessions via `pg_stat_activity` / `pg_terminate_backend` after incident approval. No claim that revocation kills already-open sessions automatically; verify application, pool and secret-store revocation separately.
4. **Rollback:** if synthetic validation fails, stop containers, restore prior restricted credentials through the operator secret vault (never grant owner access to Web), revert only the topic-branch commits or tear down the *disposable* Compose test volumes. Before dropping roles, explicitly inspect `pg_shdepend`, function ownership and grants; do not run broad `DROP OWNED` on a real shared DB. Require a forward recovery plan for existing MFA states: removing functions while Web still uses them will break login.
5. **Gate:** no production/staging rotation or grant application is authorized here. No real credentials, data, permanent RLS or financial posting.

## Test and approval checklist

- Owner migrations on disposable PG18, negative runtime DDL/migration metadata, direct and PgBouncer SCRAM real-login probes.
- Laravel `RestrictedRuntimeTest` after owner-only fixture seed: Auth login/logout, database session, company authorization and synthetic CRUD, queue enqueue, single-use MFA recovery; direct sensitive-table rights denied.
- Compose isolated DB: ephemeral migration owner, per-container Web/Worker LOGIN, non-root uid 10001, health/permission smoke, teardown with volumes.
- **NOT VERIFIED** until successful CI on exact final commit: all new code and Compose tests, robust MFA enrollment TOTP + concurrency, end-to-end queue worker consumption, existing published-row immutability, safe PUBLIC/extension functions across actual schemas, trusted audit/outbox writing, credential drill with running clients, qualified independent review.
- Keep ADR-024 PROPOSED, #7/#8/#11 OPEN, Draft PR #22 unmerged. Human independent review and separate owner merge permission remain mandatory.
