# P0-04-ACL — Identity Provider options and gated security-design decision

**Date:** 2026-11-19  
**Status:** TECHNICAL EVALUATION / PROPOSED; **NOT AN APPROVED ADR OR IMPLEMENTATION ORDER**  
**Tracked by:** [Issue #21](https://github.com/Elmo-Tech/erp-backend/issues/21), [Draft PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22), [independent assurance contract](P0-04-ACL_INDEPENDENT_ASSURANCE_CONTRACT.md), [independent enforcement production gate #11](https://github.com/Elmo-Tech/erp-backend/issues/11), [MFA operations #7](https://github.com/Elmo-Tech/erp-backend/issues/7). Human review remains absent; no real-user or deployment approval.

## 1. Current verified baseline (not a protected system)

At existing PR HEAD `a13bf42bc7d29f92147dc9ca10283c8d7e48e851`, [Actions #44521114403](https://github.com/Elmo-Tech/erp-backend/actions/runs/44521114403) has **completed/failure**. All main PG18/PgBouncer/Laravel/Compose/Docker steps were successful, but the **final intentionally blocking cross-user MFA SQL negative failed**. Any `elmo_runtime` SQL caller can provide another synthetic user and a current time-step to `p004_consume_mfa_step`, advancing `mfa_last_step` without TOTP or independently proven identity; the synthetic test rolls back. This **does not demonstrate a Laravel session bypass**. Leave the failure visible until an actually tested correction closes the attack path. PR #22 is Draft/Unmerged, Issue #21 is Open, ADR-024 is Proposed.

## 2. Selection principle: the provider alone is NOT the fix

The ERP is a first-party React 19 + Laravel 13 internal app for Egypt and Italy; the two companies are legally distinct and must not have their ledgers or payroll rights conflated. A trustworthy login/MFA issuer reduces the Web app's handling of passwords and MFA factors, but the current Web process holds `APP_KEY`, session controls and Web SQL credentials. Merely using OIDC then trusting `Auth::login`, `erp.mfa_verified`, a Web-generated header, session row or token minted by Web **fails the Web-takeover threat model**.

A complete proposal requires *both*:

- **Independent identity and MFA:** an OIDC issuer operated outside Laravel Web, with approved user onboarding/verification, enforceable strong MFA for privileged roles, step-up when needed, and issuer-owned keys/secrets.
- **Independent final enforcement:** a non-bypassable gateway and/or narrow protected executor with separate deployment, credential and policy authority. It verifies issuer tokens and fresh assurance, resolves verified subject-to-user mapping and current company membership, and executes only authorized, audited, nonce-bound commands. Laravel Web/Worker must have no alternate SQL, queue, definer-function, session-writing or API path to the protected MFA operation. Web is an untrusted relay, never a trust root.

Use Authorization Code + PKCE, fixed issuer/audience and correct provider-specific `acr/amr`; require purpose/target binding, one-time nonce and independently checked revocation for high-risk operations. A token valid for an ordinary UI request **does not** authorize arbitrary MFA enrollment or HR/Finance mutations.

## 3. IdP candidate comparison (official-source capability check, NOT tested in this ERP)

| Candidate | Officially documented capability | Likely fit / prerequisites | Security/operations tradeoff and unproven items |
|---|---|---|---|
| **Keycloak**, self-managed | OIDC Authorization Code; configurable required OTP / WebAuthn and ACR/LoA step-up authentication | **Recommended default for a disposable, self-hosted comparison PoC** if there is no existing corporate IdP and infrastructure/operations can support it | Team owns patching, configuration, HA, backup, privileged admin/audit and recovery; must test exact ACR/LoA mapping and issuer claims. Not an independently enforced SQL boundary by itself. |
| **ZITADEL**, managed or self-hosted | OIDC, TOTP and passkeys; cloud and self-host options | **Strong alternative** where managed identity operations or a hybrid are preferred | Need data residency/contract/operational approvals for external hosting, concrete MFA/step-up claims and account recovery tests, and an independent ERP protected executor. |
| **authentik**, self-managed | OIDC provider, Authorization Code, PKCE, configurable authentication flows | Viable for an organization already operating authentik or preferring its application-access features | Need policy/step-up claim and custom flow verification in a disposable integration, plus all independent-enforcement and admin-custody controls. |
| **Microsoft Entra ID**, corporate managed | OIDC, Conditional Access authentication strengths and authentication context for step-up | **Prefer evaluation first if both companies already have an approved Entra tenant and appropriate licenses and admins** | Conditional Access needs licensing (typically P1), tenant policy configuration, allowed user federation/cross-tenant model, and strict application-side claim enforcement. An `acrs` value without a protective Conditional Access policy is not sufficient proof. |

**Not assessed / cannot claim:** cost quotes, company licenses and Microsoft-365 adoption, intended production hosting, EU/Egypt identity-data location, compliance approval, actual `acr/amr` formats, recovery operator identity, SSO directory availability, project-specific load, end-to-end deployment, or resilience. These require owner/IT facts and human security/legal verification. No vendor has been selected.

### Evidence references (official docs, inspected 2026-11-19)

- Keycloak OIDC: https://www.keycloak.org/securing-apps/oidc-layers ; admin manual for OTP/WebAuthn/ACR/LoA: https://www.keycloak.org/docs/latest/server_admin/
- ZITADEL: https://zitadel.com/docs and https://zitadel.com/docs/guides/integrate/login-ui/mfa
- authentik OIDC/Authorization Code: https://docs.goauthentik.io/add-secure-apps/providers/oauth2
- Microsoft Entra authentication context: https://learn.microsoft.com/en-us/entra/identity-platform/developer-guide-conditional-access-authentication-context
- Microsoft Entra authentication strengths/licensing: https://learn.microsoft.com/en-us/azure/active-directory/authentication/concept-authentication-strengths

These sources describe **vendor features**, not evidence that Elmo ERP already has a safe integration or a reviewed security boundary.

## 4. Proposed decision rule and narrow design PoC

**Proposed evaluation order, not owner approval:** (1) inventory the two companies' current identity providers, licenses, operators, residency requirements and approved HR identity-verification controls; (2) reuse an existing approved corporate IdP when it demonstrably meets the trust contract; (3) otherwise compare a disposable Keycloak deployment against managed/self-hosted ZITADEL on the same adversarial acceptance suite. Keep authentik as a viable existing-operations option. Do not choose a paid or hosted service merely from this document.

**PoC scope only after explicit owner decision and reviewer threat-model agreement:** a separately tracked, disposable proof using synthetic users and protected example command, with (a) independent issuer-controlled Authorization Code + PKCE and mandatory MFA; (b) independently validating gateway/command executor with no Web-held signing/admin keys, isolated service role/network; (c) purpose + target + user + company + expiry + nonce + revoke checks; (d) PostgreSQL denied EXECUTE of all three `p004_*` mutators to `elmo_runtime` in the proposed security architecture; (e) test a compromised Web process that forges `erp.mfa_verified`, Auth session or a Web-held APP_KEY claim; protected command MUST DENY; (f) legitimate fresh MFA subject succeeds exactly once and cannot access another company's financial resources; (g) outage, key rotation, issuer mismatch, skew and session revocation DENY. **No real data or production secrets.**

**Pass/fail acceptance matrix:**

| Gate | Must be independently evidenced at exact SHA |
|---|---|
| P01 — Service impersonation | Web SQL caller cannot advance any user's MFA replay state by guessed step or direct/definer path; direct PG18 and SCRAM PgBouncer 42501 evidence |
| P02 — Independent assurance | Verified subject + strong MFA/step-up policy + intended issuer/audience/ACR claims verified *outside compromised Web* |
| P03 — Endpoint non-bypassability | A Web process with code execution, session access, APP_KEY and SQL credentials cannot mint privileged proof or cause a protected command via an alternate route |
| P04 — Ownership and scoped access | Egypt/Italy role and ledger distinctions enforced at the protected point; user ID and tenant are not accepted merely from Laravel input |
| P05 — Replay and lifecycle | Wrong subject/purpose, expired proof, missing/duplicate nonce, concurrent replay, revoked factor/session/membership, verifier outage all deny |
| P06 — Existing controls | PG18 migrations, synthetic upgrade/rollback, company FK, role permissions, Laravel/React regressions, Compose/Docker and prior security negatives evaluated, with no waiver |
| P07 — Governance | Qualified independent PostgreSQL/MFA-session/financial-controls review, recovery dual approval, owner selection; separate explicit merge and release permissions |

## 5. Review decisions still required

1. **Existing approved IdP?** Separate Egypt/Italy account directories or one corporate identity tenant? User provisioning, HR identity verification, support/recovery responsibilities.
2. **Hosting choice:** self-hosted Keycloak versus managed/self-hosted ZITADEL or existing corporate Entra/authentik; explicit approved data processing location, operator competence, monitoring/backup/HA ownership and licensing.
3. **Enforcement topology:** independent gateway vs narrow executor (or both), secrets and DB-role custody, network non-bypassability, role revocation and audit evidence. No extra microservice expansion beyond demonstrable need.
4. **Factor policy:** mandatory privileged MFA, reset/dual approval, acceptable strong methods, provider-specific fresh assurance/ACR, rotation and non-secret audit.
5. **Financial gate:** Worker journal write privileges and posted immutability (unmerged PR #14) are a distinct NO-GO not solved by any IdP.

**Next precise action:** owner identifies whether an approved corporate IdP already exists, and nominates qualified independent PostgreSQL, MFA/session and financial controls reviewers. Reviewers inspect this matrix and the [threat model](P0-04-ACL_THREAT_MODEL_REVIEWER_PACKAGE.md); owner then records a chosen trust contract and creates an independent scoped PoC issue/branch. **Until then: design-only, PR #22 DRAFT, CI SECURITY FAIL, NO MERGE/DEPLOY/REAL USERS.**
