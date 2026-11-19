# P0-04-ACL — Security Threat Model and Independent Reviewer Package

**Status:** PROPOSED analysis / **NO-GO for development merge**; ready for owner-nominated human security review. **2026-11-19**, Issue [#21](https://github.com/Elmo-Tech/erp-backend/issues/21), Draft [PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22), [original Security Gate](P0-04-ACL_SECURITY_REVIEW_GATE.md). This is **AI-assisted**, not a formal DBA, MFA, financial-controls or owner approval. No deployment, merger, financial posting endpoint, real data, key rotation, broad architectural fix, or new broker is authorized.

## 1. Evidence register, exact execution scope

- **E01 — code:** `database/security/p004_acl_disposable.sql` creates an owner-provisioned synthetic `elmo_runtime` SCRAM LOGIN, `elmo_worker` and `elmo_mfa_executor NOLOGIN`, and three scoped `SECURITY DEFINER` functions. Its two MFA consuming functions check `session_user='elmo_runtime'` and caller-selected `p_user`; the step function checks a near-current 30-second time step but **does not receive or verify a TOTP or authenticated end-user proof**. `p004_enroll_mfa` requires an unconsumed nonexpired grant but accepts supplied encrypted factor and digests without independently verifying factor possession or identity.
- **E02 — real negative runner:** [GitHub Actions #44497530967](https://github.com/Elmo-Tech/erp-backend/actions/runs/44497530967), run 192, **COMPLETED / FAILURE** on `60cfcec55306459ee83db4268c0c53801c70868c`. Earlier PostgreSQL 18 migrations, PHPUnit, direct/SCRAM PgBouncer ACL and restricted Laravel suites, Compose, Docker and health steps were **SUCCESS**. The final security step printed `SECURITY FAIL` after an `elmo_runtime` cross-user function call and exited 1 after rolling back. This is a **confirmed security counterexample**, not a passing mitigation.
- **E03 — diagnostic hygiene:** the same log reported `Array to string conversion` at `tests/integration/p004_mfa_cross_user_negative.php:37`, caused by comparing `source` ID with the whole `target` PDO row. The separate one-line comparison to `target['id']` removes the warning **without changing the intentionally failing security assertion, transaction rollback or function**. New-HEAD CI must be checked separately; do not inherit E02 success/failure results by SHA.
- **E04 — application:** `app/Domain/Auth/MfaService.php` locally verifies TOTP before calling `p004_consume_mfa_step(user_id, step)`; `app/Http/Controllers/AuthController.php` stores `erp.pending_user` and `erp.mfa_verified` in Laravel session and calls `Auth::guard('web')->login`. These are in-process checks and cannot provide a separate assurance boundary against arbitrary Web PHP execution.
- **E05 — privilege limits:** `elmo_runtime` has no raw DML on users/MFA recovery/grants/audit/journals, and `elmo_mfa_executor` has NOLOGIN but owns the definer functions. Those controls reduce raw SQL surface; the definer EXECUTE path is still an exposed authority. Web can SELECT `users.password` hashes and `users.mfa_secret` ciphertext and holds `APP_KEY`; the hash is not a plaintext password. `elmo_worker` can DML draft journal tables, with posted-row guards in unmerged [PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14). Future grants/functions and live role/extension inventory remain unreviewed.

**Observed proof boundary:** a synthetic `elmo_runtime` connection without a TOTP and without a distinct end-user/session assertion changed another enrolled synthetic user's `mfa_last_step` *within a transaction*; rollback preserved fixture state. **NOT demonstrated by this test:** authenticated session issuance, privileged endpoint acceptance, password bypass, recovery-code brute force or persisted customer impact. Severity **CRITICAL remains a provisional security-review gate**, not a proved authentication-bypass classification. Confirmed impact is integrity manipulation of replay-prevention state, with conditional availability effects (see below).

## 2. Threat model — assets, trust, assumptions

```text
Untrusted internet / browser / TOTP input
        |
        v
[Laravel Web PHP process] -- (runtime SQL credentials) --> [PostgreSQL 18]
 | untrusted after compromise                            |-- elmo_runtime LOGIN
 | APP_KEY, password-hash/MFA ciphertext reads            |-- SECURITY DEFINER p004_* [NOLOGIN owner]
 | can set erp.mfa_verified / Auth::login                  |-- sessions, grants, MFA state
 | session/queue access                                   |-- elmo_worker (different SQL role)
 +--- current Laravel sessions (DB or Redis, Web-managed)  +-- accounting data
 |
 +---- needs independently trusted identity + MFA assurance ----+
                                                                |
[Trusted identity / MFA issuer, key custody, independent verifier and
 privileged action enforcement outside Web trust domain] <--- NOT BUILT
[Trusted operator dual-approval for enrollment/recovery] <----- NOT VERIFIED
```

- **Assets:** authentic user-to-subject identity binding, factor secrets, last accepted TOTP step, recovery digests, approved enrollment grants, privileged sessions, revocation/version state, immutable security audit, tenant/company authorization, financial journal immutability.
- **Adversaries:** A) external API attacker without credentials; B) any principal able to use the leaked/shared `elmo_runtime` SQL credential; C) fully compromised Laravel PHP process with code execution, runtime DB access, session controls and `APP_KEY`; D) compromised Worker. B and C are stronger than a normal user submitting an OTP. The negative test exercises **B**, not a full simulated C.
- **Boundaries:** client to Web (untrusted input); Web to PostgreSQL via one **service** `session_user`, not a person; Web to definer functions (privilege elevation without trusted subject); Web to Laravel session (same-process authority, not independent); APP_KEY in Web (cannot anchor a Web-immune token); trusted operator/issuer to factor and session authority (must have separate identity/key/transport and operator controls); Worker to financial tables (not a Web privilege but not immutable-posting proof).
- **Trusted computing base required for a future solution:** independent identity proof, MFA possession verification, assurance/signing key custody, session assertion issuer/verifier, single-use counter/revocation store and final privileged-operation enforcement must be inaccessible to compromised Web execution. Merely moving the key to an adjacent container while allowing Web to request arbitrary assertions is insufficient.
- **PostgreSQL-specific assumptions to challenge:** `session_user` only proves use of the shared service credential; time-window and monotonic-counter checks are **not** TOTP proofs; SECURITY DEFINER / REVOKE PUBLIC / fixed `search_path` limit exposure, not who the target human is; PgBouncer/SCRAM authenticate the SQL service, not the end-user; company composite FKs validate relationships, not actor authorization.

## 3. Impact and severity review

| Scenario | Source-based assessment | Impact / preconditions | Status |
| --- | --- | --- | --- |
| Cross-user `mfa_last_step` advance with guessed current step | SQL function accepts arbitrary `p_user` and `p_step` in ±1 step without code | Unauthorized integrity change to MFA replay state under any `elmo_runtime` SQL caller | **CONFIRMED synthetic transaction/ROLLBACK** |
| Replay-state manipulation and availability | Advancing state blocks later TOTP steps <= the stored value; an attacker can repeatedly request near-current or +1 values when permitted | Legitimate login challenges can be rejected temporarily or repeatedly, and replay tracking is corrupted. Need clock-skew/ongoing attack and recovery-fallback tests to quantify duration; do not assert permanent account lockout | **Plausible consequence; end-to-end impact NOT VERIFIED** |
| Recovery-code consumption | `p004_consume_recovery(p_user,p_hash)` requires a matching existing 64-character HMAC digest; Web knows `APP_KEY` and can compute it **if** it knows/intercepts the code | Single-use recovery may be consumed for another subject if digest is known, causing denial of recovery for that code; no blind hash guessing implied | **Missing subject binding source-confirmed; exploitation NOT TESTED** |
| Enrollment takeover / grant consumption | `p004_enroll_mfa` needs a real pending unexpired grant and valid-shaped encrypted secret + 8 unique digests; checks shapes/step, not independent user identity/TOTP | Attacker with Web runtime and required material could consume grant and register attacker-chosen factor or disrupt onboarding; grant creation itself is not exposed to Web | **Conditional risk; end-to-end takeover NOT PROVEN** |
| Direct authentication bypass via SQL step probe | Probe never obtains Laravel authenticated session or accesses a protected endpoint | Do **not** label this single test `Authentication Bypass` | **NOT PROVEN** |
| Web-process takeover of session guard | Web PHP presently owns `Auth::login` and `erp.mfa_verified`, and can access DB `sessions`; APP_KEY is in Web | An arbitrary-code-compromised Web could subvert Web-local login/session checks. This is a **separate, unclosed #11 architecture risk**, not a claim that the SQL probe showed it | **Source-based risk; adversarial E2E proof NOT VERIFIED** |
| Worker posted-journal mutation | Worker DML on `journal_entries/lines` and `PR #14` is unmerged | Posted immutability/financial accuracy cannot be claimed; additionally `accounting_periods.updated_at` UPDATE exception needs human review | **High-risk gap / NOT VERIFIED guard** |

**Classification proposal for human reviewer:** confirmed unauthorized MFA-state write with integrity impact and conditionally repeatable availability impact; **HIGH confirmed minimum**, **CRITICAL provisional gate** because trust-broken issuer/session boundary may amplify impact under a Web compromise. Assign final severity/CVSS and threat assumptions only after independent review. Do not conflate a temporary TOTP rejection with permanent lockout or session bypass.

## 4. Remediation alternatives — require approval BEFORE implementation

All options must satisfy: **Web cannot mint identity/MFA assurance, Web cannot verify itself for privileged operations, Web cannot invoke privileged MFA-state mutations by user ID alone, Web never possesses issuer private keys or operator secrets, and denial is default on verifier outage**. No design is accepted yet; ADR-024 stays PROPOSED.

| Candidate | Sketch (not an implementation instruction) | Review questions / tradeoffs |
| --- | --- | --- |
| **A. Existing external identity provider + independent enforcement boundary** | Identity/MFA completed at trusted IdP; a separately controlled gateway or privileged-operation executor verifies issuer, user, MFA `acr/amr`, audience, nonce, freshness, session version and revocation; Web is a relay only. PostgreSQL factor/state changes authorized by a role inaccessible to Web. | Verify end-to-end IdP MFA policy and token audience; user provisioning/tenant memberships, fail-closed revocation, whether Web can bypass gateway or directly mutate sessions/commands; custody and availability |
| **B. Minimal dedicated assurance authority with a separately controlled verifier** | A narrowly scoped independently deployed authentication/assurance component verifies primary identity + factor, owns signing/verification material and storage credentials, issues short-lived one-use, purpose-bound claims; an independent enforcement point consumes claims for protected commands. Revoke Web EXECUTE on `p004_*` and grant only an actor-bound trusted principal. | #11 already tracks a broker **production gate**; this is a **design candidate only**. No broker creation, socket/UID prototype reuse, signing key in Web, or broad microservices without explicit review of B's OS/network/DB isolation and credentials |
| **C. DB-mediated bounded commands behind a trusted independently authenticated executor** | Keep PostgreSQL as transactional guard, but only a separate verified service role can execute audited, target-bound MFA mutations with short-lived independently authenticated subject and assurance context. Web SQL credentials have no related EXECUTE or session authoring power. | Need independent subject proof upstream, role/revocation semantics, `SECURITY DEFINER` ownership/search_path/EXECUTE re-audit and safe pooling. `current_user` or Web-supplied `user_id` is NOT sufficient |

**Explicit rejects:** calling `p004_*` from the same compromised Web after adding a Web-HMAC token; using `APP_KEY` as an issuer signature secret; trusting `session_user='elmo_runtime'` as person identity; trusting `erp.mfa_verified` or SQL `SET` context from Web; hiding/xfailing the negative; issuing a trusted assertion from current Laravel code; reintroducing the unapproved P0-03H broker; modifying production secrets. A local Web patch that only validates `p_user` against a Web session is defense in depth against normal misuse, **not** the requested independent compromise boundary.

### Proposed contract for security sign-off (NOT APPROVED)

Identity issuer and privileged action enforcer must agree the authenticated human subject and tenant/role version; secure credential/factor verification method; factor enrollment/recovery authorizer with verified operator and second-person approval; `issuer`, `audience`, `purpose`, `subject`, `factor_id/version`, `auth_time`, `mfa_time`, `session_id hash`, `jti` (one-use), short bounded expiration and clock-skew policy; independent key custody; strict service-to-service identity and transport authentication; credential rotation, replay cache, revocation on reset/rebind/role change, global logout and incident response; persistent tamper-resistant audit (no OTP, digest, APP_KEY or secrets); emergency disable/roll back and fail-closed outage handling. A claim alone does not secure a privileged endpoint if a compromised Web still performs the final operation.

**Pre-approved code scope now:** only repair the test's source/target ID comparison and add this review documentation. Do not change functions, grants, auth controller, session code, migrations or infrastructure until the owner and human reviewers approve a concrete trust contract.

## 5. Regression plan: negative tests before any future security refactor

| ID | Scenario & expected behavior | Execution evidence now |
| --- | --- | --- |
| N01 | `elmo_runtime` SQL call with another user's ID and guessed current step, without TOTP, **MUST NOT update** replay state; keep rollback and fail CI on actual change | **FAIL as defense**: Actions #44497530967; intentional blocking finding. One-line diagnostic fix requires new-head CI |
| N02 | Same-user call with guessed step but no independent proof **MUST fail** | **NOT RUN** |
| N03 | Current, previous and next step; repeated calls/time-skew/concurrent callers **MUST NOT let untrusted Web advance state** | **NOT RUN** |
| N04 | Valid user proof plus valid TOTP accepted exactly once, simultaneously replayed claim denied, rollback restores atomicity | **NOT RUN** (existing Laravel happy-path tests do not prove independent identity) |
| N05 | Cross-user recovery digest usage, including *known digest* and wrong/no digest; only correctly bound one-use recovery succeeds | **NOT RUN**; guessing digests is not a valid attack assumption |
| N06 | Expired/consumed/revoked grant and mismatched user/approver; encrypted factor or digest substitution rejected without independent approved operator + factor proof | **NOT RUN** |
| N07 | Compromised Web fakes `Auth::login`, `erp.mfa_verified`, DB session row, unsigned/forged/replayed assurance or another identity; **privileged endpoint must deny** | **NOT RUN**; #11 production gate |
| N08 | Reset/rebind invalidates old factor, recovery, sessions and signed assertions globally; APP_KEY rotation respects recovery-HMAC migration | **NOT RUN**; #7 SEC-02/03/05/08 |
| N09 | Direct PG18 and transaction-pooled SCRAM PgBouncer: Web denied MFA mutator EXECUTE after reviewed redesign; trusted service allowed only target-bound validated operation; roles/PUBLIC/extension audited | **NOT RUN**; current design intentionally grants Web EXECUTE |
| N10 | Posted journal UPDATE/DELETE, same-/cross-company attempts, audit/outbox integrity, Worker ACL+P0-04A combination, rollback and financial reconciliation | **NOT RUN**; PR #14 unmerged |
| N11 | Auth issuer or verifier outage/clock skew/failover, revoke, rate-limit and credential rotation must fail closed, no assurance bypass | **NOT RUN** |
| N12 | Existing SQL negative privilege probes, RestrictedRuntimeTest, owner migrations, P0-03 synthetic EG/IT rollback, Docker & Compose, **all pass except the deliberate N01 blocking gate until fixed** | Earlier steps **PASS on old SHA**; rerun on each new SHA |

Commands from existing workflow (safe only with its disposable test DB, owner seeds and disposable credentials): `php tests/integration/p004_laravel_fixture.php seed`; `php tests/integration/p004_mfa_cross_user_negative.php`; `php tests/integration/p004_laravel_fixture.php cleanup`. The probe refuses non-testing `elmo_erp_test` and rolls back. CI's security step preserves nonzero exit after cleanup. Never use real records or omit the security check.

## 6. Human reviewer package and decision record

**Owner action required:** nominate **GitHub usernames** for three independent specialties before sending GitHub Review Requests. Do not assume named people, acceptance, assignment, approval or merge permission. Reviewer sign-off must identify reviewed commit SHA, files, test run, findings and disposition; subsequent code commits invalidate unqualified prior review.

| Review lane | Scope to inspect | Required deliverable (currently pending) |
| --- | --- | --- |
| **PostgreSQL Security Engineer** | `p004_*` function owner/role/PUBLIC grants, `pg_proc`, default EXECUTE, `search_path`, SECURITY DEFINER call graph, immutable/privilege SQL, SCRAM/PgBouncer identity, grants of `users` columns, Worker timestamp exception, active session revocation, future migration blast radius | Reviewed SHA + privilege/attack-path findings + signed risk decision + required SQL negatives, rollback plan |
| **MFA / Session Security Engineer** | Independent primary identity + TOTP grant, recovery HMAC and `APP_KEY`, privileged session lifecycle, Laravel `Auth::login` / `erp.mfa_verified`, proof issuer and verifier custody, token binding/replay/expiry/revocation, lost-device rebind and incident path, forged Web-process tests | Reviewed SHA + approved/rejected A/B/C trust contract + threat assumptions + exploit test matrix + session/MFA risk disposition |
| **Financial Controls Reviewer** | Worker journal DML, unmerged PR #14 guards, period lock/metadata exception, audit/outbox, company EG/IT legal separation, approved posting and reconciliation invariants, deny arbitrary posting | Reviewed SHA + financial control exceptions + immutable-posting/rollback/reconciliation negatives + final controls disposition |

Reviewer artifacts to link: [Issue #21](https://github.com/Elmo-Tech/erp-backend/issues/21); [Draft PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22); [Security Gate](P0-04-ACL_SECURITY_REVIEW_GATE.md); [ACL matrix](P0-04-ACL_PRIVILEGE_MATRIX.md); this threat model; `database/security/p004_acl_disposable.sql`; `tests/integration/p004_mfa_cross_user_negative.php`; `tests/integration/p004_acl_runtime.php`; `tests/Feature/RestrictedRuntimeTest.php`; `app/Domain/Auth/MfaService.php`; `app/Http/Controllers/AuthController.php`; [CI failure #44497530967](https://github.com/Elmo-Tech/erp-backend/actions/runs/44497530967). Read [#7](https://github.com/Elmo-Tech/erp-backend/issues/7) SEC-01..08 and [#11](https://github.com/Elmo-Tech/erp-backend/issues/11) independently enforced MFA/session before choosing a design. [#8](https://github.com/Elmo-Tech/erp-backend/issues/8) remains open for PostgreSQL company isolation.

### Decision log (no fabricated approvals)

| Decision / gate | Recorded outcome |
| --- | --- |
| AI source / CI finding | Cross-user replay-state integrity mutation confirmed on synthetic run; no auth bypass proof |
| Diagnostic correction | Intended one-line test fix; must verify new exact-head CI before labeling validated |
| Broad trust-boundary redesign | **NOT APPROVED / NOT IMPLEMENTED** |
| Human DB, MFA/session, accountant reviews | **NOT REQUESTED / NOT RECORDED** until owner provides usernames |
| Owner development merge instruction | **NOT GIVEN** |
| Development integration / deployment / production | **NO-GO** |
| Draft review preparation | **GO**, evidence only |

**Acceptance gates:** owner-selected independent reviewers; explicit accepted architecture/issuer/verifier contract; adversarial negative and happy-path proofs on same SHA (including real compromised Web), no exploitable cross-user MFA-state writes; full PG18/SCRAM/Compose/recovery/financial controls; resolved review findings; exact-head CI reviewed; distinct explicit owner development merge approval. Production authorization remains a separate later decision.

**Single next human action:** owner nominates three reviewer GitHub usernames and agrees on the independent trusted identity/MFA-assurance contract review process, **without a Review Request or design implementation being sent prematurely**.
