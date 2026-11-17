## P0-03H verified test stages / production gate split — 2026-11-17

**Exact H code SHA `d71dddc158e132725f5dcc5bfea8c6f611f05888`: [CI #44084976584](https://github.com/Elmo-Tech/erp-backend/actions/runs/44084976584) currently IN PROGRESS for Docker build / health; previous migration/PHPUnit/PG18/PgBouncer/P0-03F real HTTP, queue and P0-03G steps PASS; P0-03H UID/Unix socket isolation step PASS; adversarial `p003f_lock_authorization_scope` SQL/DoS detector PASS (meaning **SECURITY FAIL detected**, not remediated). Full CI conclusion and Docker health are **NOT VERIFIED as of this addendum**. Earlier [CI #44084156000](https://github.com/Elmo-Tech/erp-backend/actions/runs/44084156000) FAIL on synthetic script path permissions; [#44084615496](https://github.com/Elmo-Tech/erp-backend/actions/runs/44084615496) FAIL only on invalid `PUBLIC` test-role lookup after isolation step PASS; both fixture errors corrected. No previous run should be attributed to this later documentation SHA.

**Separate production blocker:** [Issue #11 — independent trusted MFA assurance/session issuer](https://github.com/Elmo-Tech/erp-backend/issues/11) created. A compromised Laravel Web process can still mint sessions and `erp.mfa_verified`; the new socket PoC does not enforce sessions or implement real broker-linked Laravel HTTP. No human security review. **Development merge only candidate pending explicit owner authorization and review; Production NO GO.** Issues #7/#8 remain open, PR #10 Draft/Unmerged; ADR-022/023 PROPOSED, permanent RLS deferred. No production secrets/deploy/main/oberation.

---

## P0-03H CI #44084156000 failed on fixture workspace permissions; correction (2026-11-17)

[Exact-head CI #44084156000](https://github.com/Elmo-Tech/erp-backend/actions/runs/44084156000) at `87824bd3abcd8968eb3e728d5ce74a37a00033fa` **FAIL** in the new P0-03H UID/socket stage, because Linux system UIDs correctly cannot traverse GitHub Actions runner-private checkout directories (`python3: can't open file ... Permission denied`); all prior P0-03F/G tests in that run passed. This was a **test fixture packaging failure**, not evidence that the broker transport/assurance boundary passed or failed. Lock DoS probe and Docker checks skipped. Corrected by copying **only synthetic public test executable files** into `/tmp/p003h-fixtures` and leaving broker secrets in UID-owned 0700/0600 directory; follow-on CI **NOT VERIFIED** until its exact-SHA run finishes. No security approval, merge, deploy, production credentials or main/oberation changes.

---

# P0-03H — Bounded OS UID / Unix socket MFA broker isolation spike

**Date:** 2026-11-17. **Status:** IMPLEMENTED EXPERIMENT; new exact-head CI **NOT VERIFIED until GitHub Actions completes**. **Security verdict: NO GO**. PR #10 remains Draft/Unmerged, Issue #8 and SEC-01..08 Issue #7 OPEN. ADR-023 PROPOSED. Synthetic PostgreSQL 18 data only; no deploy or production secrets.

## Existing P0-03G reused, not replaced

- Retain `database/security/p003g_broker_poc_disposable.sql`, `tests/integration/p003g_broker_poc.php` and `tests/integration/p003g_broker_poc.py` as the original SQL/CLI factor and challenge logic and its earlier successful CI evidence ([#44076378155](https://github.com/Elmo-Tech/erp-backend/actions/runs/44076378155), [#44076989773](https://github.com/Elmo-Tech/erp-backend/actions/runs/44076989773)).
- [#44079528651](https://github.com/Elmo-Tech/erp-backend/actions/runs/44079528651) is SUCCESS for pre-H documentation HEAD `08f7ba6`; it does **not** verify P0-03H.
- P0-03F Laravel HTTP, PgBouncer and queue tests continue to run unchanged; P0-03H does not opt Laravel into an untrusted intermediary or change production auth behavior.

## Spike files, design and exact test steps

1. `tests/integration/p003h_uid_socket_spike.py`: CI root harness creates three isolated system UIDs `p003h_broker`, `p003h_web`, `p003h_outsider`; private broker directory mode 0700 and broker-only credentials/256-bit factor key file 0600; socket directory 2770, socket 0660, broker-owned. Broker and Web must report their own Linux `SO_PEERCRED` UID, and the broker only accepts `begin` / `verify` from the Web UID. Outsider is deliberately added to the shared socket group: mode alone is insufficient, peer UID must reject the outsider. Test-only admin `seed` / `revoke` run via direct broker-UID harness, **not** socket endpoints. The broker reuses the P0-03G PHP command on each request. Replies do not export a user_id or a Web-trusted `mfa_verified` property.
2. The existing P0-03G trust-mode transaction pool **must be stripped of broker-role access after the G step**, and reloaded. A second, independent PgBouncer transaction pool on port 16433 uses `auth_type=scram-sha-256` (CI-only plaintext fixtures in its protected auth file). The Web UID's wrong-password broker login is denied; its own limited SQL role cannot SELECT broker-owned accounts. This is a deliberately non-production mechanism and not a proof of network segmentation: GitHub checkout still contains synthetic test fixtures and direct PostgreSQL is reachable locally.
3. Socket matrix covers wrong password, cross-account challenge with caller-forged `user_id/mfa_verified`, valid TOTP once, replay, revoked account, double concurrent consume, broker outage fail-closed, restart with durable one-use state, forbidden socket administration and forbidden other UID. P0-03G continues to cover challenge expiry and forced audit-insert rollback on the same PG18/PgBouncer fixture.
4. `tests/integration/p003h_lock_helper_probe.py`: inspect `pg_proc` owner/`prosecdef`/`proconfig` and EXECUTE grants; use Web SQL role to pick another user's active `user_id` and `company_id`, call `p003f_lock_authorization_scope` inside a held transaction through PgBouncer, and confirm the trusted owner UPDATE is blocked under `lock_timeout=200ms`. **A successful reproduction is a SECURITY FAIL detection**, never a passing authorization design.
5. CI runs the new integration steps after P0-03G and before Docker build, protecting all the previous checks. New CI PASS/FAIL is unknown until exact-HEAD run evidence exists.

## Security assessment

| Property | Engineering interpretation |
| --- | --- |
| Separate Unix UIDs, broker file permission denial, kernel peer ID, socket admin restriction | **TEST ADDED / NOT VERIFIED on exact new HEAD**; verify CI before claiming PASS |
| Broker-owned credentials and factor key unavailable via Web UID filesystem permissions | **TEST ADDED / NOT VERIFIED** for OS access only; test-only strings in repository/workflow are NOT production secret custody |
| Real PostgreSQL18 and SCRAM PgBouncer broker credential denial | **TEST ADDED / NOT VERIFIED**; legacy trust-mode pool is explicitly de-authorized for broker in the H step |
| Broker-owned password and challenge identity; cross-user and replay; concurrency/revoke | **P0-03G PASS at earlier SHAs**, new socket-path smoke **NOT VERIFIED** |
| Expiry, rollback and sanitized audit | **P0-03G PASS at earlier SHAs**; end-to-end independent audit custody **NOT VERIFIED** |
| Broker unavailable / restart / service peer forgery | **TEST ADDED / NOT VERIFIED** |
| Laravel HTTP actual broker integration with independent identity binding | **NOT IMPLEMENTED / NOT VERIFIED**; P0-03F HTTP still uses the legacy in-process MFA service |
| Trustworthy privileged Laravel session issuance and Web-process takeover resistance | **FAIL AS SECURITY ARCHITECTURE**: `AuthController::completeLogin` can mint normal sessions and set `erp.mfa_verified` in the compromised Web process; no independent issuer/validator |
| Privileged SQL helper callable lock targets and writer DoS | **DESIGN FAIL**: Web has general EXECUTE and controls `p_user_id/p_company_id`. Live lock contention test added, exact-head evidence NOT VERIFIED |
| Production rollout, key custody, SEC-01..08, human security review | **NOT VERIFIED / NOT AUTHORIZED** |

### Independent enforcement design required before production

A compromised Web worker can ignore local Middleware or call `Auth::login`, write `sessions` via its accessible session connection and set its own `erp.mfa_verified`. Therefore no broker proof returned to the same Web process can independently establish MFA. Recommended minimal follow-up **outside P0-03H spike**:

- One isolated issuer/security gateway or authorization module **outside the compromised Web identity** must atomically consume broker-authenticated, single-use, purpose- and audience-bound proof, bind a canonical Laravel identity, authentication-time and MFA assurance, revocation epoch and session version, and issue or verify privileged session tokens in a store/with a key inaccessible to the Web UID. Use browser/client channel protections so relay takeover and stolen OTP cannot silently gain session access.
- Enforce the resulting assurance **outside the Web worker** on every privileged route, including company switching, issuance/reset and eventual financial endpoints; deny bypass by direct Web-created sessions. Remove or strictly quarantine the legacy in-process MFA/Session DB credentials when independently integrated, not in this exploratory branch.
- Introduce authority reconciliation with real Laravel identities using a trusted identity source (not caller-supplied numeric user IDs), role/membership revocation and session invalidation, IPC availability/capacity budgets, mandatory bounded timeouts, and independent issuer audit. Test replay, cross-user forgery, Web process code execution, restart, rollback, partial failure, and unavailable broker with real Laravel HTTP and PgBouncer.
- If that reaches beyond one narrow deployable broker and independent enforcement module, make it a **separate production security gate**, not a premature microservices rebuild.

### SECURITY DEFINER review disposition

The existing `public.p003f_lock_authorization_scope(bigint, uuid)` uses qualified objects, fixed SQL and `SET search_path = pg_catalog, pg_temp`. `REVOKE ALL ... FROM PUBLIC` and direct `GRANT EXECUTE ... TO p003e_web` are present in the disposable P0-03E fixture. However, the broad migration-owner `elmo` executes the definer with elevated rights. Its caller-chosen parameters let any compromised Web SQL connection test a membership and deliberately lock arbitrary active auth rows with `FOR SHARE`, blocking membership revocation. It should not be described as independently verified authorization. A non-login, least-privilege dedicated function owner, EXECUTE inheritance audit, caller-bound authenticated identity, bounded lock/statement/idle transaction/pool timeouts, lock order and per-actor admission limits require separate human review and tests. Do not silently deploy this function as-is.

## Decision / split gates

- **MFA broker practicality:** the small Unix-UID + local socket + isolated PgBouncer prototype is a **feasible experimental mechanism**, not evidence of a secure usable production broker. An independent assurance/session enforcer and trusted identity integration need a **distinct security phase**; operational complexity and failure model must be reviewed.
- **P0-03 development integration:** the preexisting verified PG18 integrity constraints, composite company FKs, restricted SQL role tests, PgBouncer/HTTP, queue and rollback code can be considered **for explicit owner-approved non-production integration after code review and risk acknowledgment**. Existing P0-03G/H experiments are not trusted auth features. **Not merged or approved**.
- **Production:** **NO GO**, regardless of CI, until independent security review, trusted issuance, MFA custody, membership-based revocation, lock-helper remediation, SEC-01..08 and deployment hardening. ADR-022 and ADR-023 remain **PROPOSED**; permanent RLS / Trusted DB Issuer remain deferred.
