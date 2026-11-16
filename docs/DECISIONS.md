# Architecture Decision Register — approved 2026-11-16

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

## Legal / accountant gates not waived
- Egypt and Italy accountants must verify chart of accounts, legal invoice schema, tax codes, FX gains/losses, intercompany reconciliation, payroll, insurance and record retention.
- Official submission and statutory filings are intentionally outside the application initially.
- Annual SaaS/prepaid contract revenue may require deferral; payment != recognized revenue.
- Confirm base currencies, timezone, policy dates and real legal company names before production seed.

## Open implementation decisions
FX rate provider and staleness policy; allowed production/staging origins for SPA auth; object storage and retention; backup RPO/RTO; migration of historic V1 data and accounting opening balances.

## P0-02 security implementation proposals — NOT APPROVED (2026-11-16)

The existing approved ADR-005 company-scoped RBAC and ADR-006 first-party Sanctum cookie/MFA remain binding. Implementation details below are **PROPOSED**, not a new approved ADR:
- Granular role/permission matrix and global management limited to company overview until explicit company finance/payroll membership is granted (see `AUTH_SECURITY_CONTRACT.md`).
- First privileged login uses password-verified, five-minute MFA enrollment with TOTP/recovery codes. Production bootstrap/recovery and administrator reset procedure require explicit security-owner signoff before rollout.
- Dev SPA and API origins localhost:5173 and localhost:8000 are isolated CI/test values only. Production origins, domains and cookie settings are still NOT APPROVED.

## P0-02 independent security-review response — **PROPOSED, NOT APPROVED** (2026-11-16)

These are implementation candidates in draft PR #6/#2 and **not** approved business/security policy. The user must explicitly approve the final decisions before P0-02 is merged:

1. **MFA mandatory roles:** `central_management`, `company_manager`, `finance`, `hr`, `external_accountant`. No privilege-based MFA bypass. Other users who voluntarily enroll continue to require MFA.
2. **Trusted first-time MFA activation:** password alone is **not** enough to register a new factor. `user_mfa_enrollment_authorizations` must have an externally verified, time-limited, unconsumed grant with an internal approval reference. No public grant-issuance API; operator issuance, second-person identity approval, logging, and revocation playbook are **not yet approved or delivered**. Provisioning references are identifiers, not PII or tokens. Challenge lasts five minutes, authorization has its own expiry.
3. **Lost-device / rebind:** prefer remaining single-use recovery code; otherwise authenticated trusted administrator + documented identity verification, second-person approval, recorded change, and revoked prior enrollment. There is **no unaudited or public reset/rebind endpoint**. Exact staff workflow and authentication criteria await explicit user approval.
4. **Central management vs consolidation:** `central_management` permits overview and switching between active companies, but not raw finance/payroll access. An additional `consolidation_viewer` active global grant is **proposed** for authorizing future consolidated *reports only*. Both grants are required; company financial detail still requires explicit same-company membership/role. Actual eliminated intercompany consolidated reports are **NOT IMPLEMENTED** in P0-02 (future M16).
5. **Data protection:** Laravel `encrypted` cast stores TOTP secrets encrypted under the application encryption key; `mfa_secret`, `mfa_last_step` are hidden from model serialization. Temporary enrollment seed is individually `Crypt::encryptString`-encrypted in sessions regardless of `SESSION_ENCRYPT`. `/enroll/start` intentionally returns the seed **once per start** so the user's authenticator can be configured; no-store response required. Recovery codes are HMAC-digested at rest and shown once on confirmation. **Key rotation** requires an approved backup/restore and dual-key compatibility plan with a staged re-encryption and fail-safe rollback; changing `APP_KEY` without this could make existing MFA secrets unrecoverable. Do not rotate production keys as part of this PR.
6. **Brute-force controls:** login per-IP (20/5 min), per-account and per IP/account pair (5/5 min). MFA invalid attempt per-account and per-session (5/5 min), per-IP (30/5 min), with start-enrollment throttled at 3/5 min. Cache-store persistence and operational response to intentionally locked accounts need a production review.

This appendix records **PROPOSED** choices and code status, not a new approved ADR. No real-user enrollment or production rollout is authorized.
