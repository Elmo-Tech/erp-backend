# P0-03E — Trusted Issuer decision and DB permissions review (2026-11-17)

**Scope:** continuation on `task/p0-03-db-security`, Backend Draft PR #10, Issue #8. This is an AI-assisted **engineering assessment**, **NOT independent human security review or approval**.

## Engineering disposition: DEFER RLS / DO NOT BUILD A SIGNER NOW

An additional DB-trusted issuer is **not necessary** to deliver the current read-only authorized-company demo or composite-FK protections. An issuer *would be necessary* if the team elects to claim RLS as a trustworthy tenant boundary against arbitrary SQL running as the Web principal. Neither a writable GUC nor a signer accessible through the same SQL principal with caller-supplied actor/company authenticates provenance. No permanent RLS, signing function, key storage or privileged issuer is introduced.

**Current interim policy:** Laravel must authenticate the actor, revalidate active membership and exact permission, enforce explicit company predicates on every company-owned query, and rely on DB composite FKs/constraints for write integrity. This approach does **not** secure data against arbitrary SQL injection if the affected principal has unscoped SELECT. Hence column/relation privileges and a complete endpoint-by-endpoint raw SQL audit remain required. Restrict finance/payroll/HR raw table access; no new financial API.

## Source-reviewed exploitability and gap ledger

| Severity / outcome | Evidence and threat | Required disposition |
| --- | --- | --- |
| HIGH / FAIL | `p003c_runtime` with `NOBYPASSRLS` still can set `app.company_id` to another legal entity and read a GUC-only RLS relation. `AuthenticatedDatabaseContext` detects the change **after** callback, too late to retract leaked reads. Existing disposable test reproduces bypass. | Never make GUC-only RLS an authorization boundary. Keep all legal SQL explicitly scoped and raw finance inaccessible. |
| HIGH / FAIL | `p003c_runtime` gets full `SELECT users`, which exposes stored password hashes, remember tokens and encrypted MFA material to arbitrary SQL. Eloquent `User::query()->first()` currently requires full row and `mfa_secret` casting; simply revoking SELECT breaks Auth/MFA paths. | Separate minimal Web/password lookup from MFA factor service and narrow column SELECT; test real routes with genuinely distinct connections. |
| HIGH / FAIL | Single runtime holds full DML on `sessions`, `jobs`, `failed_jobs`. SQL compromise can replace session/queue payloads and thus alter authority or execute worker-delivered work. | Independent session/queue infrastructure DB principals or Redis backed stores, isolated process credentials; never share worker/MFA/admin secrets with arbitrary Web SQL. |
| MEDIUM / FAIL | `UPDATE(updated_at)` on users/companies/memberships/roles is an actual write capability supplied merely for `SELECT ... FOR UPDATE`. Existing SQL audit confirms. | Remove Web timestamp UPDATE only after replacing row locking with a proven safe strategy: e.g. narrowly reviewed trusted lock function with strict actor checks, or a different lock mode plus serialization tests; do not silently weaken revocation. |
| HIGH / FAIL | MFA `enrollConfirm` consumes authorization and updates user factor and recovery codes; `verifyAndConsume` writes last TOTP step or deletes recovery code; current `p003c_runtime` denies these. | Isolated MFA operation principal, narrowly scoped stored routines or external independently authorized service, zero blanket grants. SEC-01/02 remain separate release gates. |
| HIGH / NOT VERIFIED | Owner-generated MAC probe signs inside isolated SQL; cannot prove independent authenticated Laravel issuer, claim lifetime/replay, privileged-function search_path or corrupted credential paths. A stolen valid claim may be reused within allowed transaction. | No real signer, no enablement. Reconsider only with strong threat-based justification and independent human review. |
| HIGH / NOT VERIFIED | Proposed `p003e_*` SQL identities are only CI fixtures; Laravel currently uses one default DB connection and cookie sessions/queue under that connection, so split roles are not end-to-end architecture yet. | Wire separate connections/services with secure credential distribution; test every login, MFA enrollment, TOTP and recovery workflow through both direct PG18 and PgBouncer. |

## Review of actual implementation

- `app/Domain/Companies/AuthenticatedDatabaseContext.php`: protects selected actor/company by checking live user, membership, company, roles, permission inside outermost transaction; locks rows; sets local GUC; checks later. It requires UPDATE privileges for existing `lockForUpdate`. Safe for **trusted, explicitly scoped** callback, **not** a defense against SQL injection in callback.
- `app/Http/Controllers/AuthController.php`: `login`, `pendingUser`, `hasEnrollmentGrant`, `enrollConfirm`, `verify`, `completeLogin` require password hash, user MFA state, grant consumption, factor writes, recovery-code DML, and cookie-session mutation. No MFA admin-issuance endpoint exists.
- `app/Domain/Auth/MfaService.php`: TOTP step single-consumption uses user update; recovery consumes code via delete, issue inserts fresh recovery rows; recovery HMAC derives from APP_KEY, which remains SEC-03 risk.
- `database/security/p003c_runtime_role.sql` and `p003d_runtime_grant_audit.sql` establish the **provisional** old CI grant matrix and intentional missing-MFA grants. They are not a deployable runtime model.
- `.github/workflows/ci.yml` uses loopback `auth_type=trust` for disposable PgBouncer; this is NOT a production credential security test.

## Provisional split-principal target

| Principal | Allowed purpose | Explicitly NOT authorized |
| --- | --- | --- |
| `p003e_web` | Column-selected user profile+hash for login; read active company/member/role selectors | MFA secret or factor writes; UPDATE(updated_at); sessions, jobs, raw finance |
| `p003e_mfa` | MFA factor state, enrollment grant **consume only**, recovery issue/consume | Password hashes, company role administration, queue/session access |
| `p003e_queue` | Queue/failed-job infrastructure | Users, MFA, sessions, finance and any admin grants |
| `p003e_session` | Session infrastructure only | Users, MFA, queue, business data |
| `p003e_admin` | NOLOGIN + NO DML placeholder | Direct issuance, role changes, privileged signer |

**Important:** `p003e_mfa` is not sufficient by itself for SEC-01 authorization; a compromised Web process capable of invoking arbitrary MFA operations or reading its credentials defeats process separation. `p003e_web` cannot run the existing `SELECT *` Eloquent user flows and cannot run `lockForUpdate()` on current tables. This is an intentional **integration FAIL** and requires explicit redesign, not expanded fallback grants. `p003e_mfa` column-only UPDATE permits arbitrary factor mutation *if its own SQL credential is compromised*, so real MFA must be mediated by independently authenticated/authorized commands. Role-level safeguards are necessary but not sufficient.

## Automated evidence and limitations

Baseline exact-head `3eb340f08941827b03ce3c425a6669b726c9836b`: GitHub CI #43941237508 **SUCCESS** on 2026-11-16. Its earlier code steps include PG18 migrations, both-directions EG/IT checks, actual PgBouncer transaction pooling, reused queue, revocation race, rollback, and disposable signed-claim spoofing. These results do **not** cover the new P0-03E role fixtures.

P0-03E adds disposable-only `database/security/p003e_role_partition_disposable.sql` and `p003e_role_partition_checks.sql`, plus CI loops through distinct `p003e_web/mfa/queue/session` credentials over direct PG18 and pooled PgBouncer (eight role/transport combinations). All writes are synthetic/rollback only. Tests assert privilege matrix and negative DDL/owner/finance/MFA/password/status access; smoke allowed role-specific operations. **P0-03E CI conclusion is NOT VERIFIED until an exact-commit workflow finishes.**

Not covered by P0-03E SQL role probes: Laravel multi-connection Auth/MFA endpoints under each proposed role; API-grade EG↔IT for each new role; stolen bearer claim replay/revocation under a real signer (not built); multiple-key rotation; independent credential/process isolation, production PgBouncer authentication, production-like data upgrade, Octane, independent human security review. Those remain **NOT VERIFIED**, not PASS.

## Deployment and acceptance gates

Issue #7 SEC-01..08 OPEN. ADR-022 remains PROPOSED with a recorded **interim preference to defer**. PR #10 stays DRAFT; Issue #8 OPEN. No Merge, Deployment, permanent RLS, production changes, credential rotation, `main` or `oberation` changes. Human reviewer must record independent, named review scoped to exact commit; AI comments or green CI cannot substitute.

**Next atomic task:** fix any new CI failures on this task branch; then implement a dedicated Web authentication projection and isolated MFA/session/queue connections, preserving revocation serialization without timestamp UPDATE privileges. Add real API Auth/MFA success+failure, EG↔IT, replay/concurrent revocation, rollback and pooled-worker tests before considering a release or an RLS issuer.
