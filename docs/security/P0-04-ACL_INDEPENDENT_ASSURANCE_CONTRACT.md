# P0-04-ACL — Independent Identity/MFA Assurance Contract (DESIGN CANDIDATE)

**State:** PROPOSED / SECURITY REVIEW REQUIRED / NOT APPROVED FOR IMPLEMENTATION OR MERGE  
**Date:** 2026-11-19 · [Issue #21](https://github.com/Elmo-Tech/erp-backend/issues/21) · [Draft PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22) · [Threat model](P0-04-ACL_THREAT_MODEL_REVIEWER_PACKAGE.md)

This is a *concrete review candidate*, not an accepted ADR, operational instruction, implemented gateway, IdP selection, authorization, or sign-off. Preserve ADR-020 policy principles and the existing **NO-GO** gate. No production, staging, real users, secrets, MFA enrollment, financial posting, migration, merge, or unrelated PR stacking.

## 1. Why a PHP-only/SQL-only patch would be unsafe

The exact-head [CI #44503777322](https://github.com/Elmo-Tech/erp-backend/actions/runs/44503777322), commit `50b99a5674ab7a9784a6ccc63d5364ef80308d78`, ended **COMPLETED / FAILURE**. PostgreSQL, PgBouncer SCRAM, Laravel, Docker and Compose stages succeeded; the last deliberately blocking negative demonstrated that `elmo_runtime` can call `p004_consume_mfa_step(other_user_id, current_step)` to advance another synthetic user's MFA replay state **without a TOTP**. The test rolls back; no Laravel authentication bypass or persisted real-user impact is established.

The restricted PostgreSQL LOGIN proves a *service*, not a person. `session_user`, a request-supplied `user_id`, a time-step, Laravel session flags, and a claim HMAC-signed with `APP_KEY` from the same compromised Web process cannot independently prove MFA or the intended subject. The Web runtime also has decryptable MFA material and session authority. Do not change a failing security test to green without a real enforced trust boundary.

## 2. Recommended candidate: independently administered OIDC identity authority + enforcement boundary

Prefer evaluating a mature, independently operated OIDC identity provider that performs the primary login and MFA, rather than creating another custom TOTP/session issuer inside the ERP. **Provider and deployment model are undecided.** OIDC alone is not enough: a separate, non-bypassable enforcement component must verify assurance and authorize sensitive mutations; raw Web SQL and Worker credentials must have no path around it.

```text
 Browser
   | Authorization Code + PKCE / independent MFA
   v
 [Trusted identity authority / IdP] -- signed proof, JWKS / session metadata
   |                                               |
   v                                               v
 [Independent authenticator / policy enforcement / protected executor]
   | independent key custody; subject/purpose/company/step-up checks
   | trusted DB role (not available to Laravel Web/Worker)
   v
 [PostgreSQL: scoped MFA/auth state and high-risk commands]

 [Laravel Web / React SPA] -- untrusted application relay / display
   | SQL LOGIN: minimal read + ordinary task operations
   X NO raw MFA state writes, NO p004_* EXECUTE, NO privileged session issuance
 [Queue Worker] -- separate LOGIN; no blanket posted-ledger mutation
```

The enforcement component may be an identity-aware gateway plus narrow command executor, but *must* have an independent credential, deployment/OS/network boundary, policy and audit; simply renaming a Laravel service or putting a signer next to Web is insufficient. The component must not accept a Web-provided `X-User-Id`, caller-selected subject, or Web-minted proof as its authority. Authenticated browser or trusted IdP claims must be validated independently at that boundary. Untrusted Web compromise, compromised browser and privileged insider are distinct threat models; this proposal does not promise their complete elimination.

## 3. Required claim and command validation contract

Before any protected state mutation, the independently controlled verifier MUST:

1. Validate cryptographic signature using a pinned/trusted issuer configuration and controlled JWKS/key rotation; enforce `iss`, intended `aud`, allowed algorithm, `exp`, `nbf`, freshness (`auth_time` or equivalent) and issuer-specific, tested `acr/amr` semantics. Do not assume every IdP expresses MFA in the same claims.
2. Resolve the **subject from verified issuer state** and compare it to the intended ERP user mapping; never infer identity from SQL `session_user`, `user_id` request parameters or unsafely trusted headers. Subject-to-ERP-user mappings must be unique, versioned, revocable and audited.
3. Independently enforce active identity, approved MFA level and fresh step-up for MFA enrollment/rebind, privileged HR/Finance and company access. Re-query current ERP company membership/permissions at the authorized enforcement point; a generic IdP group cannot by itself grant Egypt/Italy ledger access.
4. Bind each sensitive command to **subject + purpose + target + tenant/company where applicable + session/assurance instance + bounded TTL + one-time nonce/jti**. Verify and atomically consume the nonce before mutation; reject duplicate, expired, forged, missing, cross-target or cross-purpose proofs. A generic login claim is not a blank check to change any user's MFA state.
5. Enforce CSRF/origin/cookie policy where browser sessions are used; verify that a compromised Web worker cannot use its own network/SQL credentials to bypass the verifier or mint a privileged session. Validate revocation, logout, device loss/rebind, role changes, clock skew and verifier/IdP outage in fail-closed mode.
6. Make the state write, replay protection, authorization decision and **non-secret** audit trace atomic or explicitly compensatable. Keep private keys, IdP admin privileges, MFA seed material, recovery HMAC keys and trusted execution credentials outside the compromised-Web trust boundary.
7. Deny the `elmo_runtime` role **all MFA mutator EXECUTE** and raw MFA table UPDATE/DELETE after a reviewed migration plan. A protected executor's role must have only the necessary scoped database operations. Re-audit `PUBLIC` grants, `SECURITY DEFINER` ownership/search_path, PgBouncer pooling and future functions.

For TOTP, an IdP-managed factor may supersede the existing `p004_*` functions; do not retain exploitable Web-executable legacy procedures as a fallback. An independent verifier of *new* factors does not silently migrate or validate existing encrypted factors/recovery codes. Design cutover and rollback before removing any login path; there are no approved real-account enrollments at this point.

## 4. Required adversarial and positive acceptance tests

| ID | Evidence required (use disposable identities, both real SCRAM LOGIN and pooled paths as applicable) |
|---|---|
| A01 | `elmo_runtime` cannot execute `p004_consume_mfa_step`, `p004_consume_recovery`, or `p004_enroll_mfa`; effective PostgreSQL privileges and SQLSTATE 42501 recorded; no PUBLIC/owner privilege bypass. |
| A02 | Cross-user and **same-user** guessed time-step without independently verified MFA/identity cannot change replay state. Existing blocking test stays enabled; never special-case it into a pass. |
| A03 | Forged Web session/`erp.mfa_verified`/caller user ID, Web-held APP_KEY claim, stolen Worker credentials or forged header cannot authorize enrollment, recoveries or protected commands. |
| A04 | Valid subject with independently verified MFA succeeds *once*; replay, concurrent race, wrong subject, wrong purpose, stale assurance, expired/revoked claim and role-change are denied. |
| A05 | Privileged actions check Egypt/Italy permissions at the trusted boundary; a cross-company project assignment grants task access only, not financial access. |
| A06 | Lost-factor rebind requires distinct verified human approvals per ADR-020; revokes earlier MFA grants, sessions, codes and assertions and emits a redacted tamper-evident audit. |
| A07 | IdP/verifier/key-store unavailable, rotated key, invalid JWKS, out-of-sync clock, stale revocation, cross-origin browser attempt and failed claim validation are fail-closed. |
| A08 | Full positive login/logout and browser tests through the actual enforced path; PostgreSQL 18 direct/PgBouncer, limited Web/Worker roles, Docker, sessions/queues, migrations and synthetic rollback remain green. |
| A09 | Separate financial gate: no Web/Worker modification of posted journals (PR #14 unmerged), no period-lock bypass, audit/outbox integrity, and intercompany isolation proven before any posting API. |

Passing ordinary Laravel MFA feature tests **is not** evidence of A01–A09. All claims must be tied to exact commit SHA + runnable CI/logs and independently reviewed attack paths; success of functional tests does not override a failed security negative.

## 5. Scoped implementation sequence proposed for approval

1. **Design approval (NOW; no code):** qualified PostgreSQL, MFA/session and accounting reviewers compare options A/B/C in the existing threat model. Owner explicitly selects external IdP + executor, or another equally enforceable independent contract; decide vendor, uptime model, custody, data residency, recovery roles, cost and cutover. Keep ADR-024 PROPOSED until then.
2. **PoC in a NEW approved tracked security task:** disposable end-to-end proof of trusted issuer/validator/subject binding and denied Web SQL mutators, including negative compromised-Web cases. No broad data schema or official finance integration.
3. **Limited Auth migration under explicit scope:** replace Web-held factor/session authority only once independent authorization is operational; ensure positive and negative role/MFA tests, key rotation/revocation and safe rollback. Do not use real accounts.
4. **Financial control and human gates:** re-review Worker/posted journal/invariants and separated company rights, run full CI at final SHA, resolve all reviewer findings. Obtain separate owner permission for any development merge; staging/production is yet another authorization.

## 6. Explicit open decisions and STOP conditions

- **OPEN:** Provider choice and OIDC `acr/amr` semantics; whether the independent enforcement resides at gateway, command executor or both; trust and network isolation; DB-role design; identity mappings and revocation; availability and cost; recovery/approval governance; legal/privacy and retention assessment per country.
- **STOP:** if Laravel Web can still mutate MFA state through any direct SQL, `SECURITY DEFINER`, queue or session-issuer path; if a trusted token can be minted using Web-held secrets; if a failing negative is suppressed; if the proof depends only on `session_user`; if human review/owner merge approval is absent.
- **NO-GO:** Existing [PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22) remains Draft and unmerged, [Issue #21](https://github.com/Elmo-Tech/erp-backend/issues/21) OPEN, security [#7](https://github.com/Elmo-Tech/erp-backend/issues/7), [#8](https://github.com/Elmo-Tech/erp-backend/issues/8), [#11](https://github.com/Elmo-Tech/erp-backend/issues/11) OPEN; [PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14) unmerged, PR #20 never merge. No production deployment, employee/financial data, key or credential rotation.

## References for human design review

- [Project threat model](P0-04-ACL_THREAT_MODEL_REVIEWER_PACKAGE.md), [security review gate](P0-04-ACL_SECURITY_REVIEW_GATE.md), [role matrix](P0-04-ACL_PRIVILEGE_MATRIX.md), [trusted activation runbook](P0-02_TRUSTED_MFA_ACTIVATION_AND_RECOVERY.md).
- [OWASP MFA Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html); [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html); [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
