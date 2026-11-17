## P0-03G final CI audit and P0-03H acceptance — 2026-11-17

**P0-03G ENGINEERING PASS (CI only) / SECURITY NO GO.** The [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10) task HEAD `696074aa49495b639e09695ee45dfb710f7b33d8` passed [GitHub Actions #44076989773](https://github.com/Elmo-Tech/erp-backend/actions/runs/44076989773) **FULL SUCCESS** (PG18/PHPUnit, migrations/rollback, real PgBouncer, existing restricted Web/MFA/Session/Queue Laravel flows, P0-03G disposable broker challenge/replay/expiry/revoke/concurrent consume/audit-rollback and Web privilege denials, Docker image and API health). The prior P0-03G code HEAD `66cf966c4d95c4a7790b75d8b5332015aed75208` passed [#44076378155](https://github.com/Elmo-Tech/erp-backend/actions/runs/44076378155). GitHub compare `66cf966c4d95c4a7790b75d8b5332015aed75208..696074aa49495b639e09695ee45dfb710f7b33d8` contains **only three doc files**, not runtime code: `docs/PROJECT_STATUS.md`, `docs/handoffs/P0-03.md`, `docs/security/P0-03G_MFA_PROCESS_BOUNDARY_REVIEW.md`. This new documentation addendum itself requires fresh exact-SHA CI.

**Verified implementation LIMIT:** `p003g_broker` is a separate disposable PostgreSQL SQL principal and a CLI command PoC; subprocesses are launched under the **same CI OS user** with test-only admin commands and synthetic broker users/factors. It does **NOT** prove different Linux UID/container/mount/network permissions, protected IPC identity, real credential custody, authoritative Laravel account binding, broker-bound password/MFA login or trustworthy Laravel session issuance. Broker returns `mfa_verified` in disposable JSON, **not** an enforceable authenticated session/assertion. A fully compromised Web PHP process can currently access the in-process MFA connection, APP_KEY and session controls; it can also intercept relayed password/OTP. A signed broker result checked solely by fully compromised Web code is NOT a complete process-takeover defense.

**P0-03H scope — one bounded non-production integration spike, not a release:** (1) require a documented OS/service threat model and two test identities with separate UID or namespace/mount boundary; Web cannot read broker environment, MFA SQL credentials, factor key or DB ACL; (2) authenticated local Unix socket or equivalent strict transport with peer identity check and dedicated broker credentials, with denied Web credential/SQL direct access; (3) broker-owned challenge/identity binding, replay protection, atomic consume/audit, bounded rate/lock timeouts, durable revocation and failure-closed restart; (4) specify the **independent enforcement point** for trusted session/assurance, including attack in which Web can bypass a local middleware/flag, then demonstrate cross-user assertion forgery, reuse, revoked memberships, concurrent consume, broker-down and rollback negatives on disposable PostgreSQL18/PgBouncer + Laravel HTTP; (5) independently review `SECURITY DEFINER p003f_lock_authorization_scope` owner/search_path/EXECUTE/caller-selected locks and DoS. Report PASS/FAIL/NOT RUN at exact SHA, and separate integration permission from production security acceptance.

**Decisions:** ADR-023 **PROPOSED**, ADR-022 **PROPOSED / permanent RLS and Trusted Issuer DEFERRED**, qualified independent human security sign-off **NOT DONE**; [Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8), [Security Issue #7 / SEC-01..08](https://github.com/Elmo-Tech/erp-backend/issues/7) OPEN; PR #10 DRAFT/UNMERGED. **No merge, deploy, real accounts, permanent RLS, production secrets, key rotation, main or oberation changes are authorized by this checkpoint.** Historic entries below are preserved for audit.

---

## P0-03G executable PoC verification update (2026-11-17)

**Exact-code SHA `66cf966c4d95c4a7790b75d8b5332015aed75208` — [GitHub Actions #44076378155 FULL SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44076378155).** P0-03G CLI broker smoke, PostgreSQL 18 + PgBouncer negative Web privilege tests, broker-owned password/challenge, TOTP single-use/replay, cross-user tokens, expiry, revocation, audit/error-trigger atomic rollback, parallel consumption all succeeded within the full run, alongside prior P0-03F integration and Docker/health. **This does NOT prove OS/process isolation, credential custody, protected service transport, trusted Laravel session issuance or production security.** Earlier negative CI #44075692788 exposed fault-injection constraint setup, #44076009406 exposed missing read-only audit inspection grant; corrected. All newer documentation SHAs require independent exact-head CI. Human review still absent, PR Draft, Issues #8/#7 open, Production NO GO.

---

# P0-03G — MFA process-compromise threat model, command boundary and disposable proof

**2026-11-17. Status: PROPOSED / engineering PoC pending exact-head CI; no human security sign-off.** Related: Draft [PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10), open [Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8), release blockers [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7), proposed ADR-022 (defer permanent RLS/Trusted Issuer). Pre-P0-03G handoff SHA `07822a1e1941d68ed65d384490055675390d8386`: [CI #44072342218 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44072342218) on that exact SHA.

## Decision proposal

**Do not put production MFA behind a fake broker sharing Web credentials.** For a real release, use a **small separately deployed identity/MFA process** (not a general microservice platform): separate OS/container identity, unmounted Web MFA secrets and DB credentials, separately provisioned encryption key, inaccessible socket/process environment, narrow command interface, isolated service account, durable audit, and broker-authoritative challenge and session/assurance state. Keep Laravel as a modular monolith for business functions. Avoid permanent RLS/Trusted Issuer while ADR-022 remains proposed.

**Development merge recommendation (not approval):** existing P0-03F database integrity/least-privilege functionality may be considered for *non-production integration* only after its own code review, the new PoC regression result, documented residual HIGH risks, and explicit owner authorization. P0-03G itself is experimental and MUST NOT be treated as production-safe or as completing SEC-01..08. Security issue #7 and draft PR #10 remain open. **Production: NO GO.**

## Threat model: SQL injection is not process takeover

| Attacker control | Existing split-role boundary | Remaining or new countermeasure |
| --- | --- | --- |
| Arbitrary SQL on **Web DB connection only** | Column grants deny MFA ciphertext/recovery; Web can read password hashes, permitted companies and membership metadata; arbitrary writable custom GUC bypasses any GUC-only RLS | Explicit company predicates + live membership checks; deny sensitive columns/DDL; never enable GUC-only RLS |
| Arbitrary PHP code in **Web process** | Web can read its process env, application APP_KEY and `pgsql_mfa` credentials, call MfaService and manipulate cookies/sessions; separate SQL connections do not help | MFA SQL keys, factor encryption key and broker DB network permissions must exist **only outside** Web's Unix UID/container/mount/network boundary; disable direct MFA routes |
| Compromised Web process relaying broker requests | Can send commands, intercept passwords/OTP supplied through Web and attempt sessions; cannot be trusted merely because it passes `user_id` or a signed value it can mint | Broker **independently checks password/session proof**, challenge ownership/expiry/revocation, MFA, rate limits and replay; separately controlled session/assurance issuance, otherwise Web can bypass MFA by creating app sessions |
| Compromised broker or broker host | Full impact on broker factor state; separation only limits scope | Strict grants, service identity, independent hardened host/container controls, monitoring, rotation and human review; not proven by CI |
| Forged approvals and recovery | Test enrollment-grant row is not independent approval | Trusted accountable issuer + verifier + second-person approval + reset/rebind/all-session revocation (SEC-01/02), separate recovery HMAC/key lifecycle (SEC-03) |

## Proposed protocol (not fully implemented)

1. **Initiation:** Browser establishes a login flow; Web relays credentials to identity broker. Broker checks password against **broker-owned authoritative identity** (or independently verifies a proof minted by an identity authority whose key Web cannot access), not a raw caller-supplied `user_id`. Broker creates a CSPRNG 256-bit opaque challenge, stores **only its hash**, binds the challenge to canonical identity, authentication strength, purpose, 5-minute expiry, revocation epoch, attempts, session/device fingerprint where securely available, and audit correlation ID. A compromised Web relay cannot invent another user's successful identity proof.
2. **Verification:** Broker accepts `challenge_token + code` and independently retrieves the bound user. Reject missing/expired/consumed/revoked challenges; five attempts per challenge, bounded password failures, independent account/IP/device/ASN limits, jitter/monitoring and availability-safe rejection. Under a DB transaction lock both challenge + canonical factor, check TOTP clock window, require `step > last_step`, atomically mark consumed, emit a scrubbed audit event. Recovery codes require separate atomic one-time consume and issuance requirements.
3. **Grant:** Broker issues a one-use, audience/purpose-bound short-lived assertion via a secret **outside Web**. **A signature by itself does not stop compromised Web from creating an application session**: trusted session creator/verifier must enforce broker result, session version and MFA assurance on all privileged API paths, with replay-resistant consumption and revocation. Never rely on a Web-controlled `erp.mfa_verified` session property as the security boundary.
4. **Administrative commands:** Enrollment/rebind/recovery and factor resets require independent issuer/verifier approval, step-up and audit, not `user_id` alone. Atomic revocation must invalidate pending challenges, MFA assertions, session versions and remembered devices; membership/role changes must invalidate relevant assurance. Store structured append-only audit off the Web writable principal, with retention/tamper alerts.
5. **Failure rules:** Broker downtime fails closed for privileged operations; no fallback to legacy in-process MFA. Restart/rollback must preserve consumed/revoked durable state. Rotate keys with versioned decrypt/verify and tested migration, not APP_KEY reuse.

## Alternatives and operational cost

| Option | Advantage | Residual problem | Recommendation |
| --- | --- | --- | --- |
| Keep P0-03F split DB connections in Web | Low complexity and good SQL-only least privilege | Full Web PHP compromise steals/uses MFA DB credentials, APP_KEY and factor material | **Accept only as explicitly risk-tracked non-production baseline** |
| Same PHP app, separate config/routes or queue | Minimal refactor | Same runtime secrets or callable privileged queue; not an independent trust boundary | **Reject as security claim** |
| Small broker **process/container** with local authenticated Unix socket/mTLS, broker-owned DB + key + identity/session issuer | Real credential/process isolation possible with narrow deployment surface | Requires UID/container/network hardening, service identity, audit/monitoring, HA/failure policy and protocol implementation | **Preferred release architecture after independent review and full integration tests** |
| Fully independent IAM/MFA hosted service | Mature identity integrations if correctly configured | Higher subscription/integration/availability/contract and session verification work | Reassess if operational team cannot safely own broker |
| Signed DB issuer + permanent RLS | Can improve DB tenant assurance only with unforgeable issuer | Adds a second difficult trust and replay boundary; unrelated to MFA process separation | **Defer ADR-022** |

## Limited P0-03G executable proof (strictly disposable)

New files:
- `database/security/p003g_broker_poc_disposable.sql`: guard `elmo_erp_test` + migration owner; CI-only broker LOGIN and broker-owned tables; negative grants for existing Web/MFA/Session/Queue SQL roles.
- `tests/integration/p003g_broker_poc.php`: **separately spawned CLI** handling `begin/verify`; password verification and envelope decryption occur outside Web PHP execution in this simulated setup. Unique random hashed challenge tokens; five-minute TTL, five password failures / 5-min lock, five challenge attempts, transactional TOTP anti-replay, account epoch revocation, hashed-identity audit events. `seed/revoke/inspect` require **test-only** flag; never expose as a remote command surface.
- `tests/integration/p003g_broker_poc.py`: synthetic identities, cross-user rejection, password failure, token replay, same-step replay, expiration, revocation, concurrent verification, audit-trigger failure rollback, Web SQL privilege denial on direct PostgreSQL 18 and transaction-pooled PgBouncer.
- `.github/workflows/ci.yml`: isolated fixture step, test-only key and password, verification before Docker; ephemeral runner only.

**Critical PoC limits:** CLI subprocesses run under the **same CI runner OS account**, so it does NOT demonstrate kernel-enforced cross-UID/container isolation or a protected socket. Test-only admin commands and static synthetic broker passwords are deliberately NOT a deployment authentication design. Broker-owned `p003g_accounts` are synthetic separate identities, not wired to authoritative Laravel users. The broker **does not issue an enforceable authenticated Laravel session**; a compromised Web PHP process could still forge Web session state. No production credential provisioning, key custody, network ACLs, durable external audit, real recovery/rebind, authenticated service identity, failover, Octane or production-like data tests. All of those are **NOT VERIFIED** and must remain production blockers.

## SQL helper: independent review requirements

`public.p003f_lock_authorization_scope(bigint,uuid)` uses fixed qualified table names and `SET search_path = pg_catalog, pg_temp`, returning only a boolean, owned by the disposable migration owner and with `REVOKE ALL ... FROM PUBLIC` followed by `GRANT EXECUTE ... TO p003e_web`. This is narrower than granting Web `UPDATE(updated_at)`; however:
- **Caller-controlled target IDs:** any Web SQL can invoke the function with arbitrary IDs, discover active membership relationships from boolean responses, and acquire authorization-row `FOR SHARE` locks. Application-layer `CompanyAuthorization` checks after the helper do **not** restrict direct SQL invocations.
- **DoS/locking:** request-supplied targets and long transactions can stall owner revocation/role updates; review lock_timeout, statement_timeout, idle_in_transaction_session_timeout, rate limits, lock ordering and PgBouncer pool starvation; test under adversarial concurrent clients.
- **Privileges:** the CI migration owner is a broad owner; verify no owner BYPASSRLS/DDL surprises, function immutability/ownership transfer via dedicated NOLOGIN minimal owner, `pg_proc.proowner/prosecdef/proconfig`, `pg_get_functiondef`, EXECUTE via PUBLIC and role inheritance, default privileges, schema CREATE and extension/operator resolution. A `PUBLIC` revoke at creation does not automatically secure future replacement/regrant.
- **Isolation:** absent authenticated caller binding and permission checks inside a restricted definer, do not treat its `boolean` as an authorization oracle or production security service. Prefer a dedicated narrow authorization coordinator or function that independently authenticates the actor, is resistant to arbitrary SQL calls, and holds strictly scoped locks.

**Disposition:** Existing helper remains CI-only, *not* production-approved. Reviewer to evaluate safer alternative: move locks to a trusted authorization/session process which checks identity/permission before issuing any lock operation, or keep short DB functions with bounded targets and tighter ACLs if justified.

## Test/acceptance ledger (as of PoC introduction)

| Check | Result |
| --- | --- |
| P0-03F pre-G code `dbc0f7e`, CI #44069174715 | **PASS** on exact SHA |
| Pre-G docs handoff HEAD `07822a1`, CI #44072342218 | **PASS** on exact SHA |
| P0-03G new SQL/CLI/Python integration | **FAIL on initial G runs #44075692788 and #44076009406; corrected G run NOT VERIFIED** |
| PgBouncer transaction pool, PostgreSQL18 existing suite | **PASS on pre-G baseline**; G additions pending |
| SQL-only GUC spoofing | **FAIL** as a security boundary (reproduced earlier) |
| Real OS/process and credential isolation | **NOT VERIFIED** |
| Full real Laravel Auth/MFA trusted-session integration | **NOT IMPLEMENTED / NOT VERIFIED** |
| Recovery/reset, SEC-01..08, independent human security review | **NOT VERIFIED** |
| Production authorization/deployment/permanent RLS | **NOT AUTHORIZED** |

Next atomic action: inspect the exact new G CI job; fix any failing **synthetic PoC** step without widening grants, then separately design and test privileged-session issuer, secure service transport and OS separation; commission human security review. Do not merge, deploy, enable permanent RLS, or modify main/oberation/Production.
