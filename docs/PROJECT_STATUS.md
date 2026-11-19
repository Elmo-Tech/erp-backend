# Project Status — Source of Truth

## P0-04-ACL provider evaluation and exact-head CI evidence — 2026-11-19 (NO-GO)

**New design-only evaluation:** [IdP options and security-review decision gate](security/P0-04-ACL_IDP_OPTIONS_AND_REVIEW_DECISION.md) compares official Keycloak, ZITADEL, authentik and Microsoft Entra ID capabilities against the independent [assurance contract](security/P0-04-ACL_INDEPENDENT_ASSURANCE_CONTRACT.md) and Issue #11. This is **not** a selected IdP/approved architecture/independent human review. Proposed evaluation rule: inventory existing corporate IdP and company/IT/data-residency constraints first; if none meets the boundary, compare disposable Keycloak and ZITADEL. An IdP alone cannot prevent Web-process takeover or Web-executable MFA SQL functions.

**New verified CI:** [GitHub Actions #44521114403](https://github.com/Elmo-Tech/erp-backend/actions/runs/44521114403) is **COMPLETED / FAILURE** on exact *prior* SHA `a13bf42bc7d29f92147dc9ca10283c8d7e48e851`. The final deliberately blocking cross-user MFA security gate was the failing job step; prior migration/PgBouncer/Laravel/Compose/Docker steps passed. Confirmed synthetic arbitrary-target replay-state write remains vulnerable and is rolled back in the probe; no login bypass is proven. **Any subsequent documentation commit's exact-head CI must be checked separately**, never inherit the prior result.

**Dispositions:** Issue #21 OPEN; PR #22 DRAFT/UNMERGED; ADR-024 PROPOSED; independent reviewers, IdP selection and implementation authorization NOT RECORDED. No Auth/MFA/SQL/workflow source changes in this provider evaluation. **NO-GO merge/deploy/real users**; #7/#8/#11 open; posted journal gate #14 unmerged and #20 NEVER MERGE.

**One next action:** capture the existing IdP/IT/tenancy facts and have owner-selected independent PostgreSQL, MFA/session and financial-control reviewers assess the proposed authority/enforcer contract and provider selection; only then scope a separate synthetic adversarial PoC and seek owner approval.

---

## P0-04-ACL independent assurance design candidate — 2026-11-19 (PROPOSED; NO-GO)

At the existing [Draft PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22), the security boundary analysis now has a proposed [Independent Identity/MFA Assurance Contract](security/P0-04-ACL_INDEPENDENT_ASSURANCE_CONTRACT.md). It evaluates a separately controlled OIDC identity authority plus non-bypassable independent enforcement/privileged executor as a **design candidate only**. Owner/provider/issuer approval, security review and implementation are **NOT DONE**. No MFA SQL grants/functions, login code, finance flows, deployment or integration code changed in this documentation step.

**Exact prior HEAD evidence:** commit `50b99a5674ab7a9784a6ccc63d5364ef80308d78`, [CI #44503777322](https://github.com/Elmo-Tech/erp-backend/actions/runs/44503777322) COMPLETED **FAILURE**, step 28 cross-user MFA security negative; steps 3–27 were successful. The negative is an intentionally blocking **confirmed synthetic unauthorized MFA replay-state mutation**, rolled back, not a successful mitigation or a proven Laravel login bypass. The **new documentation commits have not received independently verified exact-head CI** at writing; status remains **NO-GO**.

**Next precise action:** independent qualified PostgreSQL, MFA/session and financial controls review of the proposed contract and known Worker/journal gaps; record a selected, explicit trusted issuer/enforcer policy and a new scoped implementation task only after owner direction. Preserve Issue #21 OPEN, PR #22 DRAFT, ADR-024 PROPOSED, #14 unmerged, #20 NEVER MERGE, security #7/#8/#11 OPEN. No user data, merged branch changes, staging or production.

---

## P0-04-ACL verification update — 2026-11-19 (NO-GO; reviewer package prepared)

Verified [GitHub Actions #44497530967](https://github.com/Elmo-Tech/erp-backend/actions/runs/44497530967) **COMPLETED / FAILURE** at exact PR #22 HEAD `60cfcec55306459ee83db4268c0c53801c70868c`. PG18 / direct + SCRAM PgBouncer / restricted Laravel Auth-MFA-Sessions-Queue / synthetic EG-IT / Compose / Docker steps succeeded; the final **intentional blocking cross-user MFA negative** failed because `elmo_runtime` successfully modified another synthetic user's `mfa_last_step` without TOTP or independently verified subject. ROLLBACK executed. A source/target ID-array comparison caused a PHP warning and is corrected in a later narrow diagnostic-only commit; no security function/ACL is changed. **New HEAD CI NOT VERIFIED until its own run is inspected**.

**Impact:** MFA replay-state integrity violation confirmed; repeated step advancement may interfere with legitimate MFA availability, but **Authentication Bypass is NOT proven by this SQL test**. Conditional recovery/enrollment vulnerabilities and independent session-issuer design remain open. [Human Threat Model / Reviewer Package](security/P0-04-ACL_THREAT_MODEL_REVIEWER_PACKAGE.md) records impact, proposed alternatives (NOT APPROVED), test plan and PostgreSQL/MFA-session/financial review tracks. No formal human reviews or owner merge consent recorded; **NO-GO** development merge / deploy / production. Issue #21 and Draft PR #22 remain open. ADR-024 PROPOSED; #7/#8/#11 OPEN; #14 UNMERGED; #20 NEVER MERGE; no financial posting API or real data.

## P0-04-ACL focused security review gate — 2026-11-19 (NO-GO)

AI-assisted security source review of prior exact-head CI [#44386318271](https://github.com/Elmo-Tech/erp-backend/actions/runs/44386318271) at `4552a964` identified a **CRITICAL** `p004_consume_mfa_step` cross-user SQL trust failure: a caller using `elmo_runtime` can supply another user's ID and a guessed current TOTP time-step without independent TOTP or session proof. `p004_enroll_mfa` and `p004_consume_recovery` also do not independently bind SQL caller to target user. A synthetic transaction-rollback negative test is now wired to fail CI **if the bypass is observed**, NOT to register a protection PASS. See [security gate matrix](security/P0-04-ACL_SECURITY_REVIEW_GATE.md) and [P0-04-ACL handoff](handoffs/P0-04-ACL.md). **New amended-head CI outcome must be checked independently.** Do not merge or approve production. Human independent review and separate owner merge order remain distinct gates.

---

## P0-04-ACL restricted-principal code candidate — 2026-11-18 (CODE CI SUCCESS; HUMAN REVIEW OPEN)

Follow-up on [Issue #21](https://github.com/Elmo-Tech/erp-backend/issues/21) / [Draft PR #22](https://github.com/Elmo-Tech/erp-backend/pull/22): replaced disposable blanket table/sequence grants with explicit Web, Worker and NOLOGIN MFA-function-owner privileges, denied Web raw MFA/Audit/Outbox/Idempotency/migration DML, added restricted-role Laravel Auth/MFA/Session/database Queue/Company CRUD checks, direct PG18 and SCRAM PgBouncer paths, negative probes, synthetic Compose owner-provisioning checks and non-root Docker USER 10001. See [P0-04-ACL privilege matrix](security/P0-04-ACL_PRIVILEGE_MATRIX.md) and [handoff](handoffs/P0-04-ACL.md).

**Exact hardened-code CI evidence (2026-11-18):** commit `0a7ee22f489bce8a9727dbec5d10e08a765f960e`, GitHub Actions [#44385647946](https://github.com/Elmo-Tech/erp-backend/actions/runs/44385647946) **COMPLETED SUCCESS**. Verified PostgreSQL 18 owner migrations then independent Web/Worker SCRAM LOGIN, Web-negative object grants, Worker synthetic EG/IT draft CRUD and FK checks, Auth/MFA enrollment/recovery and database Session/Queue/Company CRUD under real `elmo_runtime` both direct and through PgBouncer transaction pooling, runtime migration denial, synthetic Compose PG owner provisioning with nonroot UID 10001 Web and Worker, Docker image build and API health. This documentation-only update creates a later SHA; **the code CI cannot be attributed to that newer documentation SHA** until its own workflow completes.

**Security/approval boundary:** Draft PR #22 remains unmerged; independent qualified human security review and separate owner merge authorization required. Web has readable password hashes/MFA ciphertext and can call narrow functions; a Web process takeover is NOT isolated. The Worker can still change posted journals absent PR #14 and can UPDATE only the period `updated_at` column to support a P0-03 FOR SHARE trigger. No trusted audit/outbox writer, full queue worker delivery, real credential rotation or per-company SQL authorization established. ADR-024 PROPOSED; security #7/#8/#11 OPEN; #20 excluded; no production/staging deployment or real data.

---


## Exact-code CI evidence — 2026-11-18

**VERIFIED first complete candidate:** [GitHub Actions #44375723500](https://github.com/Elmo-Tech/erp-backend/actions/runs/44375723500) COMPLETED SUCCESS on exact commit 9da30bdbfeeda5591daa74547b8f069de08f1f42. Its real PostgreSQL 18 LOGIN role provision and direct role probe SUCCESS, independent PgBouncer SCRAM startup and restricted runtime probe SUCCESS, synthetic cleanup SUCCESS; pre-existing PG18 migrations, PHPUnit, P0-03 populated upgrade/rollback, two-client concurrency, real PgBouncer Auth/MFA/Session/Queue, Docker build and API health SUCCESS. Journal immutable-state proof is intentionally NOT VERIFIED because Draft PR #14 guards are absent on this independent integration-based branch. This *documentation update* creates a NEW HEAD and must get separate exact-head CI before claiming its final validation.

## P0-04-ACL independent runtime DB principal PoC — 2026-11-18 (IN PROGRESS / DRAFT)

**Issue [#21](https://github.com/Elmo-Tech/erp-backend/issues/21)**; independent branch task/p0-04-acl-runtime-principal forked from still-unchanged integration 21a085f5. Discovered original Compose injects PostgreSQL superuser owner credentials into Web API. Proposed separate SCRAM password LOGIN elmo_runtime, owner-only migrations, explicit Compose environment mapping (no .env owner credential leak), disposable SQL permission tests, real LOGIN PostgreSQL 18 and PgBouncer scram-sha-256. CI NOT VERIFIED pending exact PR head result. [Dedicated handoff](handoffs/P0-04-ACL.md).

**Security non-proofs:** The integrated baseline lacks unmerged PR #14 immutable journal guards; runtime cannot TRUNCATE/alter triggers/DDL but broad DML permissions alone do NOT prevent UPDATE of existing posted ledger rows. Cross-company FKs protect link integrity but cannot authorize runtime Web identity; same runtime role can access both EG/IT. No proof of separate Web/worker OS principals, key custody, production PgBouncer, PUBLIC function safety, operational rollback or independent human security review. No PR #14/#16/#18/#20 code incorporated. ADR-024 PROPOSED; issues #7/#8/#11 OPEN; PR #20 NEVER MERGE. No finance API, merge, deployment, real data or main/oberation changes.

---

## P0-03 Development Integration Candidate — 2026-11-17 (DRAFT / NOT MERGE APPROVED)

**Candidate code CI:** exact commit `8110437be702e3a6553f63acc0d68cb281e19543` [GitHub Actions #44110104136](https://github.com/Elmo-Tech/erp-backend/actions/runs/44110104136) **COMPLETED SUCCESS** (PG18 migrate, PHPUnit, populated synthetic Egypt/Italy upgrade + rollback/reapply, direct and pooled concurrency, PgBouncer Auth/MFA and database Session/Queue-driver regression, Docker build and API health). **Still NOT VERIFIED:** dedicated restricted-role queue worker, independent security review, production-like populated database and unsafe `SECURITY DEFINER` replacement. This documentation-only update is a new SHA and must be verified independently; do not attribute the prior code CI to it. No merge approval.

Candidate branch: `task/p0-03-development-integration-review` from verified integration baseline `5bc57535129e652f24f4991e7b90d418cef6f200`; separate from [P0-03 exploratory PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10). Scope: PostgreSQL 18 accounting/check/exclusion constraints, existing composite-FK verification (not new composite FKs), Egypt/Italy direct SQL rejection, synthetic nonempty upgrade/rollback/reapply, concurrency, pooled Laravel Auth/MFA regressions, a narrow optional PgBouncer PDO compatibility option. No expanded SQL grants, no MFA broker, no Unix UID/socket, no permanent RLS, no SECURITY DEFINER, no role partition feature flag. Laravel least-privilege role integration is deferred because `p003f_lock_authorization_scope` is demonstrated DoS-prone; no replacement is accepted here.

Evidence from **other, wider PR**: exact `77cd6efa` [CI #44085735213](https://github.com/Elmo-Tech/erp-backend/actions/runs/44085735213) completed SUCCESS for 41 steps, but its P0-03H negative test detected a security design FAIL. It is NOT evidence for this separated candidate. The candidate's own CI is **NOT VERIFIED until its Draft PR run finishes**. Prior P0-03F tests cover split SQL role operation only on the unsafe experimental fixture and must not be carried over as a sign-off. Synthetic populated upgrade and rollback is newly introduced here and must pass candidate CI.

Risk gates: migration DDL locks, invalid existing rows on nonempty upgrades, cross-company isolation, auth/session/queue compatibility, lack of independent Web-process credential isolation and trusted MFA/session issuer; SEC-01..08 [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7) and [Production gate #11](https://github.com/Elmo-Tech/erp-backend/issues/11) remain OPEN. ADR-022/023 PROPOSED, Production NO GO, no release or merge approval. No production/staging, real data, permanent RLS, `main` or `oberation` writes.

**Next action:** review candidate-only CI, add proven real pooled queue worker and safe least-privileged Web design as a separate approved step, request explicit owner authorization before any development merge.

---


## P0-02 merged for DEVELOPMENT — canonical status (2026-11-16)

**BOTH PRs MERGED to `rebuild/erp-v2`. Owner-authorized non-production integration under [ADR-021](DECISIONS.md). This is NOT a security sign-off or approval to deploy/use real accounts.**

- Backend [PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6) squash-merged as [`6c2894d46ec5f48fca8f769010698c29631dff20`](https://github.com/Elmo-Tech/erp-backend/commit/6c2894d46ec5f48fca8f769010698c29631dff20).
- Frontend [PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2) squash-merged as [`661ac4a01ecfb3f55c712575490f75cf002b160e`](https://github.com/Elmo-Tech/erp-frontend/commit/661ac4a01ecfb3f55c712575490f75cf002b160e).
- [Frontend post-merge CI #43916083490](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43916083490) **SUCCESS** at `661ac4a`. [Backend post-merge CI #43916327950](https://github.com/Elmo-Tech/erp-backend/actions/runs/43916327950) **SUCCESS** on backend merge SHA `6c2894d46ec5f48fca8f769010698c29631dff20` (verified via GitHub Actions).
- Prior task-head checks **SUCCESS**: Backend [#43915766423](https://github.com/Elmo-Tech/erp-backend/actions/runs/43915766423), Frontend [#43890384964](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43890384964), Chromium [#43890384899](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43890384899) with immutably pinned backend atomic code `ceded61c936009105b0607c022c02967e2645a6b`. The prior Chromium success was on task branches, not a postmerge production deployment.
- **SEC-01..08 still NOT resolved/verified for production**: open [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7) and read [security checklist](security/P0-02_SECURITY_REVIEW_CHECKLIST.md). SEC-01 audited independent trusted activation, SEC-02 full recovery + session revocation, SEC-03 APP_KEY/recovery HMAC rotation remain critical. **No real privileged MFA enrollment, production financial/HR functions, staging/production deploy, key rotation or secrets.**
- Existing [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4) stays OPEN to track pending security completion; [Issue #1](https://github.com/Elmo-Tech/erp-backend/issues/1) retains independent browser evidence acceptance decision. Stale docs PR #5 CLOSED without merge.
- **Next development chat:** `ERP V2 | P0-03 | Database Security`, [Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8); begin from **current integrated HEAD**, not `task/p0-02-auth-rbac`. Work on new `task/p0-03-db-security` branch, with no production actions. Operational security hardening has separate chat `ERP V2 | P0-02-SEC | MFA Operations & Audit` ([Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7)) and is a pre-release gate.
- Prior parts of this document describing Draft or pending integration are historical snapshots. **This section wins** when interpreting current state; always refresh GitHub live.

---

## Current decision — ADR-021 non-production merge approval (2026-11-16)

**APPROVED FOR DEV INTEGRATION ONLY / MERGE PENDING at this snapshot.** Owner explicitly authorized integration-only squash merges of Backend PR #6 and Frontend PR #2 into their respective `rebuild/erp-v2` branches, accepting responsibility for non-production project review. This is NOT a formal independent security review, does NOT complete SEC-01..SEC-08, and does NOT authorize real privileged user onboarding, production/staging deployment, `main`/`oberation` changes, key rotation or V1 data migration. Owner approval is for the development integration action only; security production gates remain.

Verified prior to docs: Backend CI #43895840430 SUCCESS at `12abef38eb2dd4fa5c5a6dc6ee971c58e7d9827d`; Frontend CI #43890384964 and Chromium #43890384899 SUCCESS at `653adee1c456582e8fc004d006ba62a5a2fce7c9`; Chromium pinned atomic backend `ceded61c936009105b0607c022c02967e2645a6b`. New docs-only commit requires its own CI check.

SEC-01 HIGH: no enforced dual-person auditable trusted MFA activation grant issuer/verifier/approver; SEC-02 HIGH: no audited lost-device rebind/reset plus full session kill and notifications; SEC-03 HIGH before key rotation: recovery HMAC derived from APP_KEY; SEC-04..08 exact RBAC (including own-company company_manager finance.view), session expiry/revocation, audit integrity, staging-origin checks and concurrency/qualified security review remain OPEN. No production readiness claim. Keep Issue #4 OPEN or transfer each unresolved item to tracked follow-up Issues.

**Next:** confirm PR merge SHA and CI on both integration branches; keep Issue #4 open for production security readiness. Prior text below saying no merge authorization is a historical snapshot superseded ONLY for these two development PRs.

---

**Final live GitHub reconciliation (2026-11-16):** Draft documentation PR #5 was **CLOSED WITHOUT MERGE** as superseded; [disposition comment](https://github.com/Elmo-Tech/erp-backend/pull/5#issuecomment-6920496544). Updated continuation prompt safely remains on backend task branch. [Issue #4 review record](https://github.com/Elmo-Tech/erp-backend/issues/4#issuecomment-6920512050); Backend [PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6#issuecomment-6920513211), Frontend [PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2#issuecomment-6920514285) both linked. Latest backend docs HEAD `f8511e57b65496941605e92437666fa8a9a0a267` pre-this-sync; GitHub returned no associated workflow runs at verification, thus **post-documentation HEAD CI NOT VERIFIED**. Previous backend CI #43890579327 and unchanged frontend CI/Chromium remain SUCCESS at their specified earlier SHAs. No human review requested or submitted; reviewer UNASSIGNED. No merge/deployment/P0-03.

## P0-02 Security sign-off evidence update — 2026-11-16 (CURRENT)

**Status: IN PROGRESS / OWNER-APPROVED PRINCIPLES (ADR-020) / INDEPENDENT HUMAN REVIEW NOT YET ASSIGNED OR APPROVED.** No merge, deployment, P0-03, production changes or real-user resets.

- Source reviewed: backend PR #6 at prior SHA `da5d94b7c4eeb574d534a0481e86694157b4dcad`, frontend PR #2 at `653adee1c456582e8fc004d006ba62a5a2fce7c9`. Review documents now committed on the **existing backend task branch**. New documentation-only commits must not be mistaken for newly verified CI; their checks require a separate recheck.
- Prior HEAD checks verified: [backend run #43890579327](https://github.com/Elmo-Tech/erp-backend/actions/runs/43890579327) **SUCCESS** (PostgreSQL, PHPUnit, Docker/health), [frontend #43890384964](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43890384964) **SUCCESS**, [real Chromium #43890384899](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43890384899) **SUCCESS**, with pinned backend runtime `ceded61c936009105b0607c022c02967e2645a6b`. Earlier atomic runtime [#43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049) **27 tests/233 assertions** successful. Synthetic CI only.
- Security review deliverables: [trusted MFA activation and recovery](security/P0-02_TRUSTED_MFA_ACTIVATION_AND_RECOVERY.md), [code-reconciled RBAC/consolidation matrix](security/P0-02_COMPANY_RBAC_MATRIX.md), [key/session/audit operations](security/P0-02_SECURITY_OPERATIONS_RUNBOOKS.md), [independent review checklist with findings SEC-01..08](security/P0-02_SECURITY_REVIEW_CHECKLIST.md). These are **DRAFT operational designs**, not owner-approved details or deployed controls.
- Main proposed high release blockers: enforceable two-person trusted grant issuance and full audit (SEC-01), no implemented lost-device reset/global session invalidation workflow (SEC-02), explicit APP_KEY recovery-code HMAC rotation strategy (SEC-03). Further matrix/session/audit gates in checklist. These are source-review findings requiring human validation, **not** claims of failing CI.
- Independent security reviewer: **UNASSIGNED** at inspection (GitHub PR #6/#2 requested-reviewer fields and formal reviews empty). Must be distinct from code author. Owner still must name verifier/approver/executor/auditor, approve identity checks, exact company matrix, staging/production session/cookie/origin and key/audit retention/backup details.
- Old Draft PR #5 is a stale intake; its unique chat prompt is already preserved as newer `docs/NEW_CHAT_PROMPT_P0-02.md`; **do not merge its outdated NOT STARTED statements**. Treat PR #6's docs as current and use a superseded/closed disposition with audit comment, not a merge.

**Next precise action:** Owner appoints a qualified independent human reviewer and operating-policy owners; reviewer inspects both current PR diffs and SEC-01..08, resolves blockers and records an evidence-linked verdict. Explicit merge and deployment permissions remain **separate future decisions**. Issue #4 stays OPEN.

---


## Current decision — ADR-020 owner-approved policy principles (2026-11-16)

**P0-02 IN PROGRESS; code/CI/browser verification PASS; policy principles approved; human review and deployment specifications OPEN. No PR merge/deploy/P0-03.**

- Owner explicitly approved the recommended MFA, trusted activation/identity verification, recovery/rebind, company RBAC and consolidated-report isolation, encryption key management and first-party session/HTTPS/CSRF/CORS **principles**. See [ADR-020](DECISIONS.md) and [security contract](AUTH_SECURITY_CONTRACT.md).
- Operational details **not** approved by this message: named issuer and independent approver, identity-check evidence, reset implementation, exact role-permission matrix, key-rotation/backup procedure, production origins and idle/absolute timeouts. Independent qualified human security review has not been assigned/completed.
- Verified at current previous code heads: [Backend CI #43885210439](https://github.com/Elmo-Tech/erp-backend/actions/runs/43885210439) SUCCESS (prior docs), [Frontend CI #43885394399](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394399) SUCCESS, [real Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) SUCCESS with pinned atomic backend `ceded61c936009105b0607c022c02967e2645a6b`. This *documentation-only* ADR change will trigger its own CI; report result separately.
- **Next atomic action:** nominate a qualified independent reviewer for [Backend Draft PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6) and [Frontend Draft PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2), document exact operating procedures for approval, reconcile stale Draft PR #5; then seek **separate explicit merge approval**. [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4) stays OPEN.

**Historical note:** older text below saying "owner policy approvals NOT APPROVED" was written before ADR-020 and is superseded for principles only. CI/review/production gates are unchanged.

---
## Latest verified — P0-02 atomic MFA browser integration (2026-11-16)

**In progress; no merge/deployment, no security policy approval.** This is the latest status; older E2E blocks below describe historical states.

- **Atomic MFA code verified:** backend `ceded61c936009105b0607c022c02967e2645a6b` — [PostgreSQL 18 + 27 PHPUnit tests / 233 assertions + Docker CI #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049) **SUCCESS**. Backend documentation-only HEAD before this update: `2b663033e7a5fb4db4e953635ff04e8b89dd38d3`; [post-doc CI #43882194690](https://github.com/Elmo-Tech/erp-backend/actions/runs/43882194690) **SUCCESS**.
- **Browser pin updated and tested:** frontend `.github/workflows/browser-e2e.yml` now pins **both** checkout `ref` and `git rev-parse HEAD` assertion to `ceded61c936009105b0607c022c02967e2645a6b`. Change commit: [`85141d4dab62ff13f778971fa7fd2beb28ac2de3`](https://github.com/Elmo-Tech/erp-frontend/commit/85141d4dab62ff13f778971fa7fd2beb28ac2de3).
- **Real Chromium E2E against atomic code:** [Frontend browser run #43884430301](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43884430301) **SUCCESS** at frontend `85141d4dab62ff13f778971fa7fd2beb28ac2de3`; GitHub job step `Verify immutable backend revision` **SUCCESS**, `Run real Chromium origin, CSRF, login, authorization and logout checks` **SUCCESS**. Synthetic localhost origins and disposable PostgreSQL only; NOT a production audit.
- **Frontend pipeline:** [CI #43884430300](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43884430300) **SUCCESS** at same frontend SHA.
- **Still BLOCKED for acceptance:** independent qualified human security review has not been completed or assigned; owner policy approvals (trusted enrollment operators/identity verification, recovery/rebind, company roles and central consolidation, key rotation, production cookie/sessions) are **PROPOSED**. Backend PR #6, frontend PR #2, old intake docs PR #5 remain Draft/unmerged. No permission to merge, deploy or start P0-03. [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4) stays OPEN.
- **Next precise action:** obtain owner policy decisions and assign a qualified independent human security reviewer to inspect current backend+frontend PR diffs, then resolve findings and request separate explicit merge approval.

## Latest verification — P0-02 atomic MFA enrollment follow-up (2026-11-16)

**State:** IN PROGRESS / security sign-off requested / not approved / no merge or deployment. Latest runtime code commit: `ceded61c936009105b0607c022c02967e2645a6b` on backend `task/p0-02-auth-rbac` ([Draft PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6)); frontend [Draft PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2) unchanged. Stale documentation [Draft PR #5](https://github.com/Elmo-Tech/erp-backend/pull/5) remains unmerged and must not overwrite new handoff/status.

**New review finding:** [independent follow-up issue comment #6918381867](https://github.com/Elmo-Tech/erp-backend/pull/6#issuecomment-6918381867) correctly flagged non-transactional grant consumption vs user factor write and recovery-code issuance. **FIXED in code**: `AuthController::enrollConfirm` now locks the user row with `lockForUpdate`; conditionally consumes a single-use unexpired grant; updates Eloquent-encrypted TOTP and last step; and issues eight recovery digests within one `DB::transaction`. The inner `MfaService::issueRecoveryCodes` is also transactional with one bulk insert, so future standalone code rotations roll back safely. On DB failure: generic HTTP 503, sanitized exception **class only** in warning (no secret or SQL bindings), no successful login, grant and pending challenge remain available for retry. Concurrency/role/policy review remains required.

**Backend evidence:** [CI #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049) **SUCCESS** at runtime SHA `ceded61c936009105b0607c022c02967e2645a6b`: PostgreSQL 18 migrations, PHPUnit **27 PASS / 233 assertions** including three DB-trigger failure-injection rollback/retry tests, Docker build and health smoke.

**Historical browser gap (closed by later run #43884430301):** Earlier successful Chromium #43876191068 had tested backend `0d2843c...`, not the atomic code. Frontend workflow pin was subsequently updated on `85141d4`; [Chromium #43884430301](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43884430301) now **PASS** on immutable atomic backend `ceded61c936009105b0607c022c02967e2645a6b`. Do not treat the old blocker as current.

**Release blockers:** independent qualified human security review (**requested in PR conversation, not yet completed**); explicit owner approval of all **PROPOSED** MFA activation/rebind, roles/consolidation, operational key rotation/retention/logging and real hosting/session policies. The old PR #5 must not be merged over the latest docs. Issue #4 remains OPEN; P0-03 NOT STARTED.

---

## Latest: P0-02 security review remediation — 2026-11-16

**IN PROGRESS / DRAFT / NO MERGE.** Backend [Draft PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6) and frontend [Draft PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2) continue on existing `task/p0-02-auth-rbac` branches targeting `rebuild/erp-v2`. Source of truth for this task: [handoff](handoffs/P0-02.md) and [proposed security contract](AUTH_SECURITY_CONTRACT.md).

Security review found plaintext database TOTP secrets, plaintext pending enrollment session values, incomplete MFA limits and incomplete browser enrollment evidence. Remediation code was committed on the backend through **`0d2843ccb2df9c5df3629ce3f4ad0b56dfd44cea`**: encrypted TOTP cast + hidden attributes, separately encrypted pending enrollment seed, trusted limited-use activation grant, independent per-IP/per-account limiters, no-store auth routes, explicit consolidation grant, and first-time enrollment/revocation/Egypt↔Italy/central permission tests. Frontend now clears failed-login passwords and enrollment/recovery UI secrets. **No production data or environment changed.**

**Evidence verified now:** [Backend CI #43874999124](https://github.com/Elmo-Tech/erp-backend/actions/runs/43874999124) **SUCCESS: PostgreSQL 18 migrations + 24 PHPUnit tests / 178 assertions + Docker health**. [Frontend CI #43875272312](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43875272312) **SUCCESS** on frontend `6b2b3f97c2dbe8321373cd7d4b195b95ed5f5869`. The new Chromium enrollment/recovery E2E [#43875272279](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43875272279) **SUCCESS**: the job log verifies exact pinned backend SHA, real first MFA activation, expired TOTP denial, one-time recovery reuse denial, secret DOM clearance, and previous CORS/CSRF/company restrictions. E2E workflow now **pins backend SHA `0d2843ccb2df9c5df3629ce3f4ad0b56dfd44cea`** and verifies the checkout SHA. Older Chromium E2E [#43868982519](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43868982519) passed for earlier scenarios, **not** first enrollment.

**Approval/merge gates:** `PROPOSED` first-time MFA activation/recovery ownership procedure and company/consolidation roles still need explicit owner decision; browser E2E latest must be green, independent security review required, and documentation-only [PR #5](https://github.com/Elmo-Tech/erp-backend/pull/5) must not overwrite new `IN PROGRESS` docs. The unique old next-chat prompt was carried forward in updated form, while PR #5 remains open pending owner's decision to supersede/close. P0-01 Issue #1 stays OPEN pending owner acceptance of branch E2E evidence. Do not start P0-03 or merge/deploy.

---

## Historical foundation baseline (retained for audit; not the latest task status)

**Last verified snapshot:** 2026-11-16. **P0-02 is IN PROGRESS in draft PRs; no release or merge approved.** GitHub is the canonical record; always re-check current branch heads and latest CI before beginning a new chat.

## Milestone

**Phase 0 — F00 CI/build baseline VERIFIED in merged code on `rebuild/erp-v2`; P0-01 Issue OPEN only for the expressly unverified live browser SPA→API connectivity requirement.** No staging or production deployment authorized.

| Checkpoint | Status | Evidence |
|---|---|---|
| Integration branches | **BOTH MERGED** | Backend `97ed18fbb7d644f90ca3a10323e5bee0f0469264`; frontend `f3e74934c7b0547a531197fa5a67c61055945e7c` |
| Dedicated task branches | Created in both repos | `task/p0-01-ci-baseline` (per repo; both based on `rebuild/erp-v2`) |
| Backend PR | **MERGED** (user explicitly requested merge; no formal GitHub reviews) | https://github.com/Elmo-Tech/erp-backend/pull/2 |
| Frontend PR | **MERGED** (same authorization) | https://github.com/Elmo-Tech/erp-frontend/pull/1 |
| Authentic dependency lockfiles | **COMMITTED to integration branches** using real Composer/npm registry resolution | [Composer lock](https://github.com/Elmo-Tech/erp-backend/blob/rebuild/erp-v2/composer.lock), [npm lock](https://github.com/Elmo-Tech/erp-frontend/blob/rebuild/erp-v2/package-lock.json) |
| Backend Composer install/validation | **PASS on PR HEAD** | https://github.com/Elmo-Tech/erp-backend/actions/runs/43858463818 |
| Backend PostgreSQL 18 migrations | **PASS** `migrate:fresh` on disposable CI DB | Same run |
| Backend PHPUnit | **PASS — 6 tests, 9 assertions** | Same run |
| Backend Docker build and API health smoke | **PASS** | Same run |
| Frontend `npm ci`, typecheck, Vitest, Vite build | **PASS — 4 tests in 2 files; post-merge integration CI SUCCESS** | https://github.com/Elmo-Tech/erp-frontend/actions/runs/43860361496 |
| Frontend Docker/Nginx smoke | **PASS** | Same run |
| Live browser SPA-to-backend, cookie/CORS E2E | **NOT VERIFIED** | Separate smoke checks and mocked frontend health tests do not establish full end-to-end connectivity |
| Backend post-merge CI | **SUCCESS on merged integration commit**; latest PR HEAD CI **PASS** | https://github.com/Elmo-Tech/erp-backend/actions/runs/43860404956 · https://github.com/Elmo-Tech/erp-backend/actions/runs/43858463818 |
| Production/staging | **NOT DEPLOYED** | No release approval |

## Active task and interpretation

[P0-01 — CI & reproducible builds](https://github.com/Elmo-Tech/erp-backend/issues/1): **both PRs MERGED after the user's explicit approval**. Core build/CI acceptance is VERIFIED in GitHub Actions. The GitHub Issue remains **OPEN**, because the live browser SPA-to-real-backend connectivity/cookie-CORS end-to-end requirement is **NOT VERIFIED**. The user authorized merging, **not** claiming that this missing test passed. Formal PR review submissions were absent; record the explicit user merge approval as authorization, not a code review.

Old integration-branch CI runs [Backend #43846548484](https://github.com/Elmo-Tech/erp-backend/actions/runs/43846548484) and [Frontend #43846673824](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43846673824) failed before producing accessible job steps or logs; their *original* failure reason is **NOT VERIFIED**. Newly created PR pipelines did expose missing lockfiles and passed after genuine locks were generated. The first npm 10.9.9 lock generation attempt failed with `Cannot read properties of null (reading 'edgesOut')`; npm **11.21.2** generated the lockfile and passed `npm ci`, and is pinned for frontend CI/Docker.

## Decisions and safety

No change to approved business architecture/ADR register in this task. Implementation-only CI tooling now pins npm 11.21.2 for compatibility with Node 22; backend remains PHP 8.4 / Composer 2, PostgreSQL 18. Temporary CI lockfile bootstrap workflows have been **removed** from both merged code trees. Accounting and legal isolation decisions remain unchanged. No new auth, financial posting API, customer data, or live migrations were used.

The current baseline **does not yet implement** Auth/MFA/RBAC, immutable GL posting, billing, audit outbox processing or an authorized staging rollout. The financial test validates company membership and a cross-company composite FK; broader authorization/transaction behavior remains for later tasks.

## Next exact action

**Finish P0-01 on the already merged integration branches:** re-check [post-merge Backend CI #43860404956](https://github.com/Elmo-Tech/erp-backend/actions/runs/43860404956), then perform a real isolated browser SPA→API health request and verify the intended development origin/cross-origin behavior. A passing independent API health smoke and a mocked frontend test **do not** prove this. Keep [Issue #1](https://github.com/Elmo-Tech/erp-backend/issues/1) OPEN until this evidence exists or the requirement is explicitly moved/accepted with a recorded decision. Do not deploy.

**Next distinct task:** **P0-02 — Sanctum SPA auth / MFA / company-scoped RBAC**. Authentication-specific CORS/cookies must be tested there regardless of P0-01's health connectivity check. Start in a **new chat** per work protocol.

## Mandatory session handoff

For every task, read [START_HERE.md](START_HERE.md), [WORK_PROTOCOL.md](WORK_PROTOCOL.md), the relevant [handoff](handoffs/P0-01.md) and both `AGENTS.md`. Record issue/PR/CI URLs, exact pass/fail outcomes, unresolved gates and precise next step. GitHub is authoritative over chat memory.

## P0-02 live task handoff — 2026-11-16

- [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4) is OPEN, owning security/auth work. Backend [draft PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6) and frontend [draft PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2), both targeting `rebuild/erp-v2`; integration branch has **not** changed.
- Branches: `task/p0-02-auth-rbac` in each repo, created from backend `89f862d044ef7b241426bc8eac8985bc22df96b6` and frontend `f3e74934c7b0547a531197fa5a67c61055945e7c`.
- Implemented in branches: Sanctum stateful middleware, CORS origin allowlist, session-based login/logout/me, pending MFA/TOTP/recovery enrollment, global/company role checks, company context/switch, authorization-only probe, and React login/MFA/switcher. **Not merged, not production.**
- Proposed security policy `docs/AUTH_SECURITY_CONTRACT.md` requires explicit approval (initial MFA enrollment/recovery procedure and exact company role matrix). No actual finance/payroll data endpoints.
- Backend PostgreSQL 18 migrations, PHPUnit and Docker **SUCCESS** [#43868602769](https://github.com/Elmo-Tech/erp-backend/actions/runs/43868602769) and [#43868872071](https://github.com/Elmo-Tech/erp-backend/actions/runs/43868872071). New TOTP replay tests and bearer-only token rejection **PASS** in [backend run #43869193660](https://github.com/Elmo-Tech/erp-backend/actions/runs/43869193660): **14 tests / 58 assertions**, PostgreSQL 18 migration and Docker/health all successful. New docs-only pipeline runs are distinct.
- Frontend [CI #43868982516](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43868982516) **SUCCESS** for typecheck, Vitest, Vite build and Docker/Nginx. **Real Chromium SPA ↔ Laravel API E2E SUCCESS** [#43868982519](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43868982519): synthetic localhost origins, CSRF negative, cookie session/login/logout, MFA TOTP, authorized company switching and forbidden permissions, untrusted origin CORS block. No production environment tested.
- [P0-01 Issue #1](https://github.com/Elmo-Tech/erp-backend/issues/1) remains **OPEN**: only a successful real browser-backed SPA→Laravel integration should resolve its residual requirement; do not close automatically.
- See [P0-02 handoff](handoffs/P0-02.md) for precise latest check ledger and single next action. No formal review/approval yet.

**Final security integration recheck:** Browser E2E [run #43868982519 attempt 2](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43868982519) completed **SUCCESS** with latest backend task branch, including cookie-only middleware; backend security commit CI [#43869193660](https://github.com/Elmo-Tech/erp-backend/actions/runs/43869193660) **SUCCESS** (14 tests, 58 assertions). These results do not waive formal PR review, owner approval of proposed MFA/role details, or release gates.


**Latest post-documentation reruns (2026-11-16):** Backend task branch `9cb08c27be6c56af2f5e39aab6f96476a84cd672` [CI #43876128864](https://github.com/Elmo-Tech/erp-backend/actions/runs/43876128864) **SUCCESS**; frontend task branch `364c299b236fb1cc4bea5046d878f8fa62912c10` [CI #43876190968](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43876190968) **SUCCESS** and pinned Chromium [#43876191068](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43876191068) **SUCCESS**. The immutable E2E backend code ref remains `0d2843ccb2df9c5df3629ce3f4ad0b56dfd44cea` (tests + encrypted storage security changes). These commits after the code pin only update documentation. Next CI run after this ledger update must be checked separately; no merge or approval implied.
