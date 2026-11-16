# P0-02 — Independent Security Review Checklist and Code Findings

**Date:** 2026-11-16. **Status:** PRE-REVIEW / AI-assisted static source inspection only; **NOT a qualified human security sign-off**. Backend PR #6, Frontend PR #2, old docs PR #5 remain Draft and unmerged at inspection. No deployment or P0-03.

## ADR-021 integration-only decision (2026-11-16)

Owner explicitly authorized integration-only squash merges of Backend PR #6 and Frontend PR #2 into their respective `rebuild/erp-v2` branches, accepting responsibility for non-production project review. This is NOT a formal independent security review, does NOT complete SEC-01..SEC-08, and does NOT authorize real privileged user onboarding, production/staging deployment, `main`/`oberation` changes, key rotation or V1 data migration. Owner approval is for the development integration action only; security production gates remain.

SEC-01 HIGH: no enforced dual-person auditable trusted MFA activation grant issuer/verifier/approver; SEC-02 HIGH: no audited lost-device rebind/reset plus full session kill and notifications; SEC-03 HIGH before key rotation: recovery HMAC derived from APP_KEY; SEC-04..08 exact RBAC (including own-company company_manager finance.view), session expiry/revocation, audit integrity, staging-origin checks and concurrency/qualified security review remain OPEN. No production readiness claim. Keep Issue #4 OPEN or transfer each unresolved item to tracked follow-up Issues.

**Findings below remain OPEN, not resolved by this approval.**

## Evidence reviewed (specific versions)

- Backend PR #6 HEAD at start: `da5d94b7c4eeb574d534a0481e86694157b4dcad` (subsequent commits here **docs-only**). Latest backend run [#43890579327](https://github.com/Elmo-Tech/erp-backend/actions/runs/43890579327) SUCCESS for that HEAD: PostgreSQL migration, PHPUnit, Docker/health job steps PASS.
- Latest frontend PR #2 HEAD `653adee1c456582e8fc004d006ba62a5a2fce7c9`; [Frontend #43890384964](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43890384964) and [Chromium #43890384899](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43890384899) both SUCCESS.
- Browser E2E workflow checks immutable backend `ceded61c936009105b0607c022c02967e2645a6b`; post-pin backend changes before this inspection are documentation-only per task handoff. Browser uses synthetic entities, localhost:5173 Vite, localhost:8000 Laravel and PostgreSQL 18; never represent it as staging/production.
- Inspected current `MfaService`, `AuthController`, `CompanyAuthorization`, `CompanyController`, `User`, session middleware, CORS/Sanctum config, migrations, API routes, workflow and Frontend API client. Automated checks are existing GitHub proofs; **no new tests executed by this documentation-only security review**.

## Findings — triage with a human reviewer; do not mark fixed by writing documentation

| ID | Priority | Finding / precise observed code | Required resolution / evidence |
|---|---|---|---|
| SEC-01 | **HIGH release blocker** | `user_mfa_enrollment_authorizations` has user ID, free-form `approved_reference`, expiry and consumption, but **no verifier/approver/issuer identities or independent approval proof**. `AuthController::hasEnrollmentGrant` checks only row existence/unconsumed/expiry. No production audited issuance path. | Owner assigns roles and approves identity verification; implement auditable issuance/revocation + enforced two-person authorization + tests. |
| SEC-02 | **HIGH release blocker** | No admin lost-device recovery/rebind/forced-all-session revocation endpoint or transaction; existing recovery code is one-time login factor only. | Two-person recovery/reset flow, reliable session/pending grant invalidation, user notifications and audit with failure-injection/race tests. |
| SEC-03 | **HIGH design blocker before key rotation** | `MfaService::recoveryHash` HMAC key is `config('app.key')`. Rotation of `APP_KEY` invalidates existing stored recovery digests even if Laravel decrypts old ciphertext with prior keys. | Versioned recovery HMAC/rotate or approved reissuance, offline migration and rollback tests. Never rotate production key without plan. |
| SEC-04 | **MEDIUM/HIGH policy gate** | `CompanyAuthorization::ROLES` grants `finance.view` to `company_manager`; no `finance.export`/payroll, while global central management gets only `company.view`. Matrix is technically implemented but not owner-approved. | Review and explicitly approve or change role matrix; test negative and multi-role cases. |
| SEC-05 | **MEDIUM/HIGH operational gate** | `EnsureActiveErpSession` confirms web auth, active user and mandatory MFA but does not implement an explicit absolute lifetime or account-wide forced-session revocation; session defaults/config require verification. | Specify enforced idle + absolute lifetime, global logout-on-compromise, role-change policy and multi-device tests before production. |
| SEC-06 | **MEDIUM/HIGH operational gate** | No complete tamper-evident audit event chain for grant issue/use/reset/role changes. Existing sanitized exception-class warning is not sufficient. | Enforce restricted audit sink, actor and approval linkage, privacy/retention and alerting; test no-secret logging. |
| SEC-07 | **REVIEW / PENDING** | Browser E2E uses correct SHA pin and seeded synthetic approval; it cannot prove real operator identity, consent, production origins/cookies or all concurrency interleavings. | Independent human code review, staging with approved hosts and test accounts, explicit browser security checks. |
| SEC-08 | **REVIEW / PENDING** | `MfaService::verifyAndConsume` does atomic DB update/delete for code replay; initial enrollment uses DB transaction + user lock, with PostgreSQL injected-error tests. Multi-process concurrency and time-skew cases deserve independent review. | Human assess racing enrollment confirms/recovery use and locking order; add contention tests if necessary. |

**Not evidenced as open defects in this review:** encrypted-at-rest `mfa_secret` cast/hidden serialization; encrypted pending seed; fail-closed enrollment DB error; bounded MFA throttling; single-use codes; cross-company access negatives. Those are implemented and supported by earlier CI — re-open only with new evidence.

## Reviewer checklist — human must record PASS/FAIL/NOT VERIFIED for each

- [ ] Authentication: identity enumeration, brute-force per account/IP, CSRF/cookie behavior, fixation, logout, idle/absolute sessions and multi-device revocation.
- [ ] MFA: bootstrap trusted issuance, separate human approval, TOTP replay/skew, recovery single use, seed/codes redaction, DB rollback, concurrent confirmation, compromised-factor handling.
- [ ] Secrets: DB encrypted cast, session ciphertext, backup/APP_KEY compatibility, recovery HMAC key rotation, no sensitive logs/client storage/browser history.
- [ ] Company access: both EG↔IT manager/accountant negatives; inactive/revoked membership/user, role change mid-session, global vs company grant union, jobs/exports, no authority from UI.
- [ ] Consolidation: two independent active global grants, no raw finance/payroll or real report amounts in P0-02; later M16 must do correct intercompany elimination and data classification.
- [ ] APIs/config: only cookie session, no bearer bypass, strict CORS, fail-closed authorization, sensitive no-store, dev-vs-prod origins/cookies, proxy trust.
- [ ] Audit/ops: attribution, verifier/approver separation, retention, account recovery/reset/notifications, session kill-switch, incident escalation and restore drills.
- [ ] Review conduct: inspect **both PR diffs**, identify responsible author/reviewer, record reviewer username/date/commit SHAs, reproduce findings, verify fixes and independent decision. Automated CI is necessary but not sufficient.
- [ ] Explicit owner authorization separately for merge; deployment requires another separate authorization. Do not mark Ready automatically.

## Reviewer assignment

**GitHub formal review submissions on Backend PR #6 and Frontend PR #2 at inspection: NONE. Requested reviewers in both PR metadata: NONE. Named independent reviewer: NOT ASSIGNED.** Do not nominate an unverified username or pretend AI is the reviewer.

## Documentation PR #5 conflict

Old Draft documentation PR #5 contains obsolete `NOT STARTED` statements and overlaps status/handoff/roadmap. Its unique next-chat prompt already exists in updated form on PR #6 branch (`docs/NEW_CHAT_PROMPT_P0-02.md`). Do not merge PR #5 over newer docs. Close as superseded only after confirming no unique useful material is lost; any close operation is **not a merge**. Record link in Issue #4.

## Required sign-off template

Reviewer: **UNASSIGNED** · Date: **PENDING** · Backend reviewed SHA: **PENDING** · Frontend reviewed SHA: **PENDING** · Finding outcomes: **PENDING** · Decision: **NOT APPROVED**. Reviewer must provide evidence-linked PR review and owner must separately authorize merge.
