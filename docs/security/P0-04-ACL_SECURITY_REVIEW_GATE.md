# P0-04-ACL | AI-assisted security review gate (2026-11-19) 

## 2026-11-19 — Verified diagnostic CI and threat-model refinement (after original gate text)

[GitHub Actions #44497530967](https://github.com/Elmo-Tech/erp-backend/actions/runs/44497530967) is **COMPLETED / FAILURE** at **exact** SHA `60cfcec55306459ee83db4268c0c53801c70868c`. Earlier PG18 / restricted-role / PgBouncer / Laravel / Compose / Docker steps succeeded. The cross-user negative test printed **SECURITY FAIL** after observing a synthetic `mfa_last_step` change and executing ROLLBACK. The same job logs include a non-security PHP warning caused by comparing `source` ID to the `target` PDO array rather than `target['id']`; the one-line diagnostic correction does **not** authorize bypassing or weakening the failing security gate. Any new commit requires independently verified exact-head CI.

**Refined impact statement:** confirmed arbitrary-target MFA replay-state integrity manipulation by an authenticated `elmo_runtime` SQL caller; TOTP login availability may be temporarily or repeatedly degraded by advancing a fresh step, depending on timing and repeated access. This probe **does not** demonstrate Laravel login or privileged-session bypass, recovery-code guessing, or persisted changes. Critical remains **provisional gate severity** pending independent adjudication; see [Threat Model and Independent Reviewer Package](P0-04-ACL_THREAT_MODEL_REVIEWER_PACKAGE.md) for attack preconditions, trusted issuer alternatives, recovery/enrollment caveats, regression plan and three human review lanes. No independently enforced MFA/session trust contract has been approved.

**Disposition: NO-GO for development merge; GO for presentation to independent qualified human DB/application-security reviewers.** Not production/staging sign-off. Scope is existing Issue #21 and Draft PR #22 only. Baseline examined: `4552a964a6ac6e3e79b221d528be9734034dd286`; original exact-head [CI #44386318271](https://github.com/Elmo-Tech/erp-backend/actions/runs/44386318271) COMPLETED SUCCESS on 2026-11-18, but did **not** test cross-user MFA SQL invocation. New synthetic negative regression test is configured to **FAIL CI** if it confirms the vulnerability. Verify the diagnostic CI run on the amended HEAD separately; do not relabel the failure as a passing security test.

This is **AI-assisted source/test review, NOT an independent human review**. No project owner merge permission, production credentials, deployment, financial posting API, permanent RLS, new issue or topic branch.

## Evidence, trust boundary and severity

| Control / question | Result | Severity | Source-grounded reason |
| --- | --- | --- | --- |
| Dedicated Web and Worker SCRAM LOGIN; nonroot UID 10001 | PASS | LOW (residual) | [SQL principals](../../database/security/p004_acl_disposable.sql), [Compose](../../compose.yaml), [CI #44386318271](https://github.com/Elmo-Tech/erp-backend/actions/runs/44386318271) |
| Web direct MFA/Audit/Outbox/Migrations/Journal writes denied | PASS | MEDIUM (residual) | Object grants plus actual [has_table_privilege and denied DML](../../tests/integration/p004_acl_runtime.php) / [restricted Laravel test](../../tests/Feature/RestrictedRuntimeTest.php) |
| `p004_consume_mfa_step` independently enforces cross-user MFA proof | **FAIL** | **CRITICAL** | Only verifies `session_user`, target-supplied ID and approximate time; no TOTP/session claim is verified by SQL. New [negative regression](../../tests/integration/p004_mfa_cross_user_negative.php) executes a transaction-rolled-back cross-user probe |
| `p004_consume_recovery` independently binds target to authenticated user | **FAIL** | **HIGH** | Target ID and HMAC hash from caller, no DB identity/assurance. Hash knowledge is required; do not claim blind recovery consumption |
| `p004_enroll_mfa` proves target identity/verified TOTP independently | **FAIL** | **HIGH** | Consumes an existing trusted-operator grant atomically for a single target, but trusts caller-supplied target/encrypted secret/step/digests; shape/time is not TOTP verification, and any Web SQL caller can use a pending grant |
| NOLOGIN definer, qualified tables and fixed search_path | PASS | MEDIUM (residual) | Three scoped `SECURITY DEFINER` functions owned by `elmo_mfa_executor` (no SQL LOGIN). This is not an authentication boundary |
| Web `users.password` hash / `users.mfa_secret` ciphertext confidentiality from compromised Web | **FAIL** | **HIGH** | Full-table Web SELECT grants both columns; [User model encrypted cast](../../app/Models/User.php) and [MfaService](../../app/Domain/Auth/MfaService.php) decrypt MFA using same Web process APP_KEY; bcrypt hash is not a plaintext password |
| PUBLIC function EXECUTE and role inheritance on current synthetic schema | PASS | MEDIUM (residual) | [SQL REVOKE plus existing effective function count (Web 3/Worker 0), role flag/membership probes](../../tests/integration/p004_acl_runtime.php); counts include PUBLIC/effective privileges |
| Future functions, extensions, other schemas and default PUBLIC EXECUTE | NOT VERIFIED | HIGH | Current snapshot count cannot attest to future function defaults or all extension/search-path surfaces; migrations need a reviewed re-audit |
| Worker journal entries/lines DML and posted immutability | **FAIL** | **HIGH** | Worker receives SELECT/INSERT/UPDATE/DELETE; guards are only in unmerged [Draft PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14), and CI explicitly emits NOT VERIFIED for posted rows. No posted-write safety proven |
| Worker `UPDATE(accounting_periods.updated_at)` exception | **FAIL (least-privilege exception)** | MEDIUM | Required by existing P0-03 invoker `SELECT ... FOR SHARE` trigger; financial state/date/lock columns denied in runtime probe, but metadata timestamp remains writable |
| Per-company SQL authorization | NOT VERIFIED | HIGH | Composite FKs protect relations, not actor identity. Generic Web and Worker DB roles are not company-scoped |
| Full Queue Consumption with Worker | NOT VERIFIED | MEDIUM | Database queue enqueue, worker DB permissions, Redis worker startup proven; dequeue/process/ack/failure end-to-end not proven |
| Active-client credential rotation, revoke, reconnect, pool draining | NOT VERIFIED | HIGH | Only [operator runbook](P0-04-ACL_PRIVILEGE_MATRIX.md); no live-session revocation drill |
| Trusted audit/outbox/idempotency writer | NOT VERIFIED | HIGH | Web/Worker denied raw writes but no separate trusted writer designed and tested |
| Independently approved human security review | NOT VERIFIED | **CRITICAL gate** | [PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22) has no formal review submissions; AI analysis cannot substitute |
| Separate explicit owner merge instruction | NOT VERIFIED | **BLOCKING gate** | No authorization. Do not merge merely after human review or CI |

## Explicit function assessment

- `p004_consume_mfa_step(p_user,p_step)`: **CRITICAL** SQL-caller-selected target and time window permit replay-state advancement without the actual TOTP. The time step can be computed from the database clock; absence of `p_code` or trusted independently bound claim is decisive. Artificial state is rolled back in the bounded diagnostic.
- `p004_consume_recovery(p_user,p_hash)`: **HIGH** function cannot differentiate target user/session. It does require knowledge of an existing 64-character digest; a compromised Web process has APP_KEY and may compute a digest for a known/intercepted code, but random recovery hashes cannot be trivially guessed.
- `p004_enroll_mfa(p_user,p_encrypted_secret,p_step,p_digests)`: **HIGH** grant issuance cannot be done by Web directly, and the unique `user_id` primary key bounds one active grant per user; however a caller of the function can bind arbitrary appropriately shaped factor material to any target with a valid grant. Neither grant provenance nor TOTP/session assurance is independently verified in PostgreSQL.

## CI method and limitations

Pre-existing workflow: owner migrations on disposable PG18; real Web/Worker SCRAM logins, direct and PgBouncer transaction pooling; `has_table_privilege`, `has_column_privilege`, `has_function_privilege`, no member/owner impersonation, SQLSTATE 42501 negatives, real Laravel Auth/MFA/Session/Queue-enqueue and Compose non-root smoke. **Function EXECUTE effective privileges ARE checked**, not only REVOKE text. Standard PHPUnit initially runs as owner; the separate RestrictedRuntimeTest runs as actual Web. No full Worker queue consumption or posted journal immutability proof.

New last workflow step seeds three **synthetic** owner-created users, invokes `p004_consume_mfa_step` as a real `elmo_runtime` LOGIN against another enrolled user's ID with no TOTP, checks state in the same transaction, **rolls back**, and fails the CI on unauthorized success. It is a **security finding** and never a positive protection result. No customer data.

## Resolution / human review handoff

**Do not fix by trusting `session_user`, a Web-supplied user ID, a Web-generated signed claim using APP_KEY, or DB GRANTs alone.** An independently trusted authority (e.g. separate MFA/session assurance actor/key and verified caller-to-subject binding) must be designed, human-reviewed and regression-tested. The present SQL function interface does not contain the independent proof; adding a token minted by the same compromised Web would not fix it. A narrow safe functional patch is **not** defensible without that independent trust/issuer contract; fail closed at the security/merge gate instead.

The owner should **identify and explicitly request** separate qualified PostgreSQL privilege/SECURITY DEFINER and application MFA/session security reviewers (and accounting controls reviewer for Worker/posted immutability). Do not assume names, assignments, availability, approval or merge consent. Record reviewed HEAD, findings, fixes, new exact-head CI, sign-off identities and then obtain **separate explicit owner merge instruction**.

Keep [Issue #21](https://github.com/Elmo-Tech/erp-backend/issues/21) OPEN, [PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22) DRAFT. P0-04A [PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14) unmerged, [PR #20](https://github.com/Elmo-Tech/erp-backend/pull/20) NEVER MERGE, ADR-024 PROPOSED, security [#7](https://github.com/Elmo-Tech/erp-backend/issues/7), [#8](https://github.com/Elmo-Tech/erp-backend/issues/8), [#11](https://github.com/Elmo-Tech/erp-backend/issues/11) OPEN. No `main`, `oberation`, integration change, deploy or real data.
