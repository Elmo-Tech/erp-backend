# Architecture Decision Register — approved 2026-11-16

## ADR-022 — PostgreSQL company isolation and optional RLS context (PROPOSED; 2026-11-16)

**Status: PROPOSED ENGINEERING DESIGN ONLY — NOT APPROVED FOR PRODUCTION.** Cross-company physical integrity derives from already-approved ADR-003 and ADR-004. This ADR records new implementation choices for independent review, not a silent owner sign-off.

**Proposal:** keep `company_id NOT NULL` on legal rows and composite same-company foreign keys, with additive parent candidate keys; enforce safe accounting domains, positive single-sided journal lines, date containment and company-scoped exclusion ranges in PostgreSQL 18. No posting APIs or immutable journal assertions are claimed. Allow nullable company_id only on explicitly global audit/outbox events and global user/MFA/currency entities, never as a loophole to write legal ledger rows.

**RLS option under evaluation:** a distinct NOLOGIN/NOBYPASSRLS, non-table-owner least-privileged runtime role can use `current_setting('app.company_id',true)` only after *trusted membership authorization* and per-transaction `SET LOCAL`, and only for a transaction encompassing all company SQL. Missing tenant context must return zero legal rows. Do NOT allow clients to select arbitrary company context by directly writing a GUC: RLS based solely on a writable custom GUC is not authorization. Global auth, user login/MFA and cross-company consolidation flows need separate vetted context boundaries. A rollback-only disposable fiscal_years SELECT policy probe is included in P0-03; no application RLS is activated.

**Alternatives:** application-only scoping (insufficient defence in depth), unconditional RLS now (unsafe for unaudited session/worker pool paths), security-definer context functions (require separate threat modeling), and database-per-company (greater operational overhead, conflicts with chosen ADR-003 shared schema). **Decision gate:** prove actual Laravel/queue/PgBouncer transaction-pool behavior, separate app/migration roles and GRANT matrix, authorized context provenance, concurrency and role-bypass negatives before requesting explicit approval for durable RLS. No production roles/key/secret changes authorized.

**Evidence:** [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8), [Draft PR #10](https://github.com/Elmo-Tech/erp-backend/pull/10), [handoff](handoffs/P0-03.md). Base CI [#43917199722](https://github.com/Elmo-Tech/erp-backend/actions/runs/43917199722) PASS; new P0-03 CI NOT VERIFIED in this snapshot. Production blockers SEC-01..08 remain open in [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7). No Merge/Deploy approval.

---


## ADR-020 operational elaboration — 2026-11-16 (PROPOSED details; original approval unchanged)

**The six security principles in ADR-020 remain APPROVED. This addition does not approve operational specifications, code, reviewers, merges or production release.** The following implementation-specific documents are prepared for owner decision and independent security inspection:

- [Trusted MFA activation/recovery](security/P0-02_TRUSTED_MFA_ACTIVATION_AND_RECOVERY.md): assign four named roles (identity verifier, second independent approver, authorized issuer, auditor); approve independent verification channels and non-secret evidence/retention; define grant TTL and auditable issuance/reset/rebind. Existing grant row is NOT proof of independent human approval.
- [Company RBAC matrix](security/P0-02_COMPANY_RBAC_MATRIX.md): source-derived mapping (notably `company_manager` own-company `finance.view`), company isolation and strictly separate global `central_management` + `consolidation_viewer` for future report authorization. No financial aggregation is implemented.
- [Key/session/audit operational runbooks](security/P0-02_SECURITY_OPERATIONS_RUNBOOKS.md): custom recovery-code HMAC currently uses `APP_KEY`; key rotation must preserve or consciously reissue digests, **not** simply assume encrypted-cast old-key support. Production origins/timeouts, all-device session invalidation, audit/event retention and owner roles remain undecided.
- [Security review checklist](security/P0-02_SECURITY_REVIEW_CHECKLIST.md): SEC-01..SEC-08 source observations, separate human reviewer, code/findings evidence and independent verdict required.

**Decision gates OPEN:** precise role matrix; names/identity verification/second authorization; auditable recovery and forced-session revocation implementation; key custody/rotation/backups; approved production cookie/sessions/CORS; audit/retention; qualified independent reviewer. No new approved ADR or implicit technical authorization is recorded by these proposals.

---


This reflects explicit business-owner approval to start the ERP V2 rebuild.

| ADR | Decision |
|---|---|
| 001 | Laravel 13, React 19 + TypeScript + Vite, PostgreSQL 18, Redis |
| 002 | Modular Laravel monolith + independently deployed React SPA |
| 003 | Shared PostgreSQL schema, UUIDv7 domain IDs, `company_id` for legal records, composite same-company FKs |
| 004 | Two independent legal entities Egypt and Italy, with management consolidation eliminating intercompany activity |
| 005 | RBAC with company scoped grants; company managers, central management, limited external accountants |
| 006 | First-party Sanctum SPA cookie sessions, CSRF; MFA for privileged accounts; no browser localStorage tokens |
| 007 | Versioned REST `/api/v1`, OpenAPI-derived frontend types |
| 008 | ISO currencies and decimal money, historical snapshot FX and configurable reference/override |
| 009 | Financial records immutable once issued/posted; versioned corrections/reversals and full audit history |
| 010 | Transactional outbox, Redis queues, idempotent billing and financial mutations |
| 011 | Task-timer + manual time entries, PM approval, separate attendance and flexible scheduling |
| 012 | Custom project milestones trigger Finance-reviewed invoice drafts; recurring SaaS/maintenance/hosting invoices are automatic |
| 013 | Tax documents prepared/exported; external accountants file officially outside ERP |
| 014 | Intercompany monthly Egypt-to-Italy service invoice plus project-specific charges |
| 015 | No client portal, asset inventory, automatic bank transaction import or provider API integration initially |
| 016 | Docker + Coolify; staging isolated from production; no V1 cutover until independently approved and reconciled |
| 017 | Tailwind 4, shadcn/ui, TanStack Query/Router, React Hook Form/Zod for eventual frontend design system |
| 018 | Phase 0 foundation then lead-to-cash, execution/HR, SaaS/support, full accounting/payroll/analytics |
| 019 | **Approved 2026-11-16:** GitHub-first cross-chat continuity: one ChatGPT chat per tracked Issue/task, dedicated topic branches and PRs, mandatory status/decision/handoff files and test evidence. See `docs/WORK_PROTOCOL.md`. |
| 020 | **Approved 2026-11-16 (POLICY PRINCIPLES ONLY):** privileged MFA, trusted first activation, audited/two-person lost-device recovery, company RBAC/consolidation isolation, protected key lifecycle, HTTPS/session security. Exact operations/security review/merges and deployments remain separately gated. |
| 021 | **Approved 2026-11-16 (INTEGRATION ONLY):** merge P0-02 PRs #6 backend/#2 frontend to `rebuild/erp-v2`; all SEC-01..08 security work remains open; no real users/deployment. |

## ADR-021 execution record — non-production P0-02 merges (2026-11-16)

Owner-authorized merges were **EXECUTED**: Backend PR #6 squash [`6c2894d`](https://github.com/Elmo-Tech/erp-backend/commit/6c2894d46ec5f48fca8f769010698c29631dff20) and Frontend PR #2 squash [`661ac4a`](https://github.com/Elmo-Tech/erp-frontend/commit/661ac4a01ecfb3f55c712575490f75cf002b160e), both on `rebuild/erp-v2`. **No independent security review was performed, no production or staging deployment authorized.** SEC-01..08 remain open in [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7). Development can continue on [P0-03 Issue #8](https://github.com/Elmo-Tech/erp-backend/issues/8) without activating real privileged users.

This execution record supersedes ADR-021's earlier future-tense "merge pending" text but **does not** waive any production gate.

---

## ADR-021 — Owner-directed P0-02 non-production integration acceptance (2026-11-16)

**APPROVED: merge the two specified PRs to development integration ONLY. NOT production/sign-off.**

Owner explicitly authorized integration-only squash merges of Backend PR #6 and Frontend PR #2 into their respective `rebuild/erp-v2` branches, accepting responsibility for non-production project review. This is NOT a formal independent security review, does NOT complete SEC-01..SEC-08, and does NOT authorize real privileged user onboarding, production/staging deployment, `main`/`oberation` changes, key rotation or V1 data migration. Owner approval is for the development integration action only; security production gates remain.

### Exact scope

- Squash merge Backend [PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6) and Frontend [PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2) into `rebuild/erp-v2` after verifying current CI and mergeability. Draft-to-ready transition solely to enable requested merge is permitted.
- No release, deployment, production account configuration or real data; no changes to `main` or `oberation`.
- Owner assumes accountability for integration acceptance but has **not supplied an independent security reviewer**. Do not claim independent review happened.

### Deferred security and release work

SEC-01 HIGH: no enforced dual-person auditable trusted MFA activation grant issuer/verifier/approver; SEC-02 HIGH: no audited lost-device rebind/reset plus full session kill and notifications; SEC-03 HIGH before key rotation: recovery HMAC derived from APP_KEY; SEC-04..08 exact RBAC (including own-company company_manager finance.view), session expiry/revocation, audit integrity, staging-origin checks and concurrency/qualified security review remain OPEN. No production readiness claim. Keep Issue #4 OPEN or transfer each unresolved item to tracked follow-up Issues.

### Verified prior code

Verified prior to docs: Backend CI #43895840430 SUCCESS at `12abef38eb2dd4fa5c5a6dc6ee971c58e7d9827d`; Frontend CI #43890384964 and Chromium #43890384899 SUCCESS at `653adee1c456582e8fc004d006ba62a5a2fce7c9`; Chromium pinned atomic backend `ceded61c936009105b0607c022c02967e2645a6b`. New docs-only commit requires its own CI check.

Actual merge SHAs and post-merge CI results must be recorded in the task handoff/status after the operation; approval by itself is not evidence of a merge.

---

## ADR-020 — Owner-approved P0-02 security policy principles (2026-11-16)

**Status: APPROVED — policy principles ONLY.** The ERP owner explicitly replied "اعتمدتها" to the proposed MFA, recovery, RBAC, cryptography and session-security principles. This is **NOT** approval of a specific operator, exact role-permission matrix, infrastructure/production parameters, completed human security review, PR merge, release, deployment or migration of real accounts.

### Approved requirements

1. **Mandatory MFA:** require TOTP/MFA for `central_management`, `company_manager` (Egypt/Italy), `finance`, `hr`, and `external_accountant` users; no privileged bypass. Once enrolled, MFA cannot silently be disabled to avoid step-up.
2. **Trusted first activation:** password alone cannot register an initial privileged factor. A designated, accountable trusted operator must verify identity and issue a time-limited, single-use, auditable and revocable authorization after independent authorization/approval. No public grant-issuance API.
3. **Account recovery/rebinding:** prioritize valid single-use recovery codes. Lost device with no code requires verified identity, a trusted administrator and a second independent approval, followed by invalidation of old MFA factors/codes/sessions as appropriate, audit entry and user notification. No self-service bypass or undocumented emergency reset.
4. **Egypt / Italy and consolidated RBAC:** separate legal-company membership and financial/payroll permissions. Managers and external accountants remain scoped to their company, with external accountants denied payroll. Global `central_management` may view/select both companies but has **no implied raw finance/payroll access**. Future consolidated-report permission requires an additional explicit `consolidation_viewer` global grant; detailed financial data still needs company-specific authorization. The actual consolidated report computation is outside P0-02.
5. **Encryption key management:** protect encryption keys in approved secret storage, maintain secure recoverable backups, document old-key compatibility, audited/staged rotation/re-encryption, recovery/restore testing and rollback before production. **No APP_KEY change/rotation is authorized by this decision.**
6. **Session/security baseline:** first-party Sanctum cookie sessions, CSRF, HTTPS and Secure/HttpOnly cookie attributes as appropriate, a restricted CORS origin allowlist, explicit server-enforced session expiry/revocation and sensitive-response no-store controls. The browser is never the authorization source of truth.

### Still OPEN — requires detailed specification, implementation or separate approval

- Named operator and independent approver, identity evidence requirements, issuance/revocation UI or operator procedure, audit/retention and incident/lockout runbooks.
- Exact recovery/rebind administrative implementation, approval evidence, factor invalidation and notification channels.
- Precise per-role/grant permission matrix and consolidated report fields/data classes (the granular matrix in `AUTH_SECURITY_CONTRACT.md` is still **PROPOSED** until separately reviewed).
- Key custody architecture, backups/restore drill, rotation schedule, dual-key strategy and monitoring (no production rotation now).
- Exact staging/production hostnames/CORS/cookie domains, SameSite/Secure handling, session idle/absolute timeout, account provisioning and rate-limit lockout response.
- **Independent qualified human security reviewer**, fixes and sign-off of the backend/frontend diffs. Passing CI is evidence, not a human review.
- **Separate explicit owner authorization** for PR merging and, later, for staging/production deployment. Issue #4 remains OPEN and all related PRs remain Draft until gates are closed.

### Evidence/status at time of approval

- Backend atomic code `ceded61c936009105b0607c022c02967e2645a6b` validated by [CI #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049): PostgreSQL 18, 27 PHPUnit tests / 233 assertions, Docker.
- Frontend browser flow and immutable backend pin validated by [Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) and [Frontend CI #43885394399](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394399).
- Backend documentation head before this decision: `df24ef918e535e37e15a42d749cfd894ab847144`; [CI #43885210439](https://github.com/Elmo-Tech/erp-backend/actions/runs/43885210439) PASS.

**Supersession rule:** Previous P0-02 `ALL PROPOSED / NONE APPROVED` passages below are historical drafts. They do **not** override this decision. Their unchecked boxes indicate operational/review work still outstanding, **not** rejection of these approved principles.

---
## Legal / accountant gates not waived
- Egypt and Italy accountants must verify chart of accounts, legal invoice schema, tax codes, FX gains/losses, intercompany reconciliation, payroll, insurance and record retention.
- Official submission and statutory filings are intentionally outside the application initially.
- Annual SaaS/prepaid contract revenue may require deferral; payment != recognized revenue.
- Confirm base currencies, timezone, policy dates and real legal company names before production seed.

## Open implementation decisions
FX rate provider and staleness policy; allowed production/staging origins for SPA auth; object storage and retention; backup RPO/RTO; migration of historic V1 data and accounting opening balances.

## P0-02 earlier implementation proposals — HISTORICAL; see approved ADR-020 (2026-11-16)

The existing approved ADR-005 company-scoped RBAC and ADR-006 first-party Sanctum cookie/MFA remain binding. Implementation details below are **PROPOSED**, not a new approved ADR:
- Granular role/permission matrix and global management limited to company overview until explicit company finance/payroll membership is granted (see `AUTH_SECURITY_CONTRACT.md`).
- First privileged login uses password-verified, five-minute MFA enrollment with TOTP/recovery codes. Production bootstrap/recovery and administrator reset procedure require explicit security-owner signoff before rollout.
- Dev SPA and API origins localhost:5173 and localhost:8000 are isolated CI/test values only. Production origins, domains and cookie settings are still NOT APPROVED.

## P0-02 earlier security-review response — HISTORICAL PROPOSALS (2026-11-16)

These are implementation candidates in draft PR #6/#2 and **not** approved business/security policy. The user must explicitly approve the final decisions before P0-02 is merged:

1. **MFA mandatory roles:** `central_management`, `company_manager`, `finance`, `hr`, `external_accountant`. No privilege-based MFA bypass. Other users who voluntarily enroll continue to require MFA.
2. **Trusted first-time MFA activation:** password alone is **not** enough to register a new factor. `user_mfa_enrollment_authorizations` must have an externally verified, time-limited, unconsumed grant with an internal approval reference. No public grant-issuance API; operator issuance, second-person identity approval, logging, and revocation playbook are **not yet approved or delivered**. Provisioning references are identifiers, not PII or tokens. Challenge lasts five minutes, authorization has its own expiry.
3. **Lost-device / rebind:** prefer remaining single-use recovery code; otherwise authenticated trusted administrator + documented identity verification, second-person approval, recorded change, and revoked prior enrollment. There is **no unaudited or public reset/rebind endpoint**. Exact staff workflow and authentication criteria await explicit user approval.
4. **Central management vs consolidation:** `central_management` permits overview and switching between active companies, but not raw finance/payroll access. An additional `consolidation_viewer` active global grant is **proposed** for authorizing future consolidated *reports only*. Both grants are required; company financial detail still requires explicit same-company membership/role. Actual eliminated intercompany consolidated reports are **NOT IMPLEMENTED** in P0-02 (future M16).
5. **Data protection:** Laravel `encrypted` cast stores TOTP secrets encrypted under the application encryption key; `mfa_secret`, `mfa_last_step` are hidden from model serialization. Temporary enrollment seed is individually `Crypt::encryptString`-encrypted in sessions regardless of `SESSION_ENCRYPT`. `/enroll/start` intentionally returns the seed **once per start** so the user's authenticator can be configured; no-store response required. Recovery codes are HMAC-digested at rest and shown once on confirmation. **Key rotation** requires an approved backup/restore and dual-key compatibility plan with a staged re-encryption and fail-safe rollback; changing `APP_KEY` without this could make existing MFA secrets unrecoverable. Do not rotate production keys as part of this PR.
6. **Brute-force controls:** login per-IP (20/5 min), per-account and per IP/account pair (5/5 min). MFA invalid attempt per-account and per-session (5/5 min), per-IP (30/5 min), with start-enrollment throttled at 3/5 min. Cache-store persistence and operational response to intentionally locked accounts need a production review.

This historical appendix predates ADR-020. Its underlying high-level principles were subsequently approved in ADR-020; detailed operational choices still require review. No real-user enrollment or production rollout is authorized.

## P0-02 remaining operational approval checklist — PRINCIPLES APPROVED in ADR-020; DETAILS OPEN (2026-11-16)

**ADR-020 grants owner approval to the six security policy principles only.** Unchecked tasks below represent precise policy procedures, thresholds, ownership, evidence or deployment settings awaiting specification and independent review; do not interpret them as unapproved principles. Previous code defaults are not automatically production-approved:

- [ ] **Mandatory privileged MFA:** require TOTP/recovery for central management, Egypt/Italy company managers, finance, HR and external accountants; determine whether any additional staff roles or temporary step-up exceptions need coverage and whether optional MFA stays enforceable once enrolled.
- [ ] **Trusted initial activation:** identify named accountable operator(s), an independent second approver and proof-of-identity method; confirm 10-minute authorization TTL vs five-minute password challenge, approval reference/audit retention, notification, revoke/reissue pathway and emergency lockout response. The current code has **no public issuance API** and does not implement human verification.
- [ ] **Lost-device recovery/rebind:** define single-use recovery-code-first procedure, multi-person identity verification for administrative reset, invalidation of previous factors/sessions/codes, immutable audit log/notification and denial of unapproved emergency bypass. No admin reset route exists in P0-02.
- [ ] **Egypt/Italy legal-entity RBAC:** approve role-permission mapping and company manager scope; external accountant own-company finance/export **but no payroll**; central management both-company overview **without** implicit raw financial details. Explicitly decide whether `consolidation_viewer` + `central_management` gates future consolidated reports and what data classifications the report may expose. Actual consolidated reporting belongs to M16, not P0-02.
- [ ] **Operations/cryptography:** approve APP_KEY secure key storage, backup/restore, old-key compatibility and staged re-encryption/rotation, logging metadata/retention/redaction, rate-limit lockout/unlock procedure and recovery-code handling/storage.
- [ ] **Sessions/environments:** approve real staging/production SPA/API origins, HTTPS Secure/SameSite/domain settings, idle/absolute session lifetime, clock skew and production account provisioning/revocation. CI localhost origins do not authorize a deployment.
- [ ] **Independent sign-off and release separation:** assign a qualified human security reviewer different from the implementer to inspect transaction/race behavior, secret handling, browser pin and role isolation. Resolve findings before marking Ready; obtain separate **explicit owner permission for merge**, then separate deployment permission. No authorization implied by this checklist.

**Atomicity implementation is engineering remediation, not an owner security policy approval.** PostgreSQL #43881203049 PASS at `ceded61c936009105b0607c022c02967e2645a6b` (27/233, including rollback/retry injection); Chromium #43876191068 pinned previous `0d2843c` (historical only); the newer [Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) has successfully tested atomic code `ceded61c` with an immutable pin. ADR-020 is now approved for *principles*; keep PRs Draft until independent review and separate merge approval.
