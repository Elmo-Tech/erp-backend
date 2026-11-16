# Elmo Tech ERP V2 — Start Here

> **Canonical project handoff:** read this page at the beginning of EVERY fresh chat, task, or engineering session.
> Scope: both repositories, not just the backend. **GitHub is the durable source of truth, not conversation memory.**

## Where the system lives

| Code | URL | Primary purpose |
|---|---|---|
| Backend | https://github.com/Elmo-Tech/erp-backend/tree/rebuild/erp-v2 | Laravel 13 API, data, business rules, accounting, canonical documentation |
| Frontend | https://github.com/Elmo-Tech/erp-frontend/tree/rebuild/erp-v2 | React 19 + Vite + TypeScript UI |
| Work tracker | https://github.com/Elmo-Tech/erp-backend/issues | Shared cross-repository task/issues index |
| Integration branch | `rebuild/erp-v2` in BOTH repos | Review target; do not modify `main` or `oberation` |

## Mandatory reading order

1. [PROJECT_STATUS.md](PROJECT_STATUS.md) — current reality, next task, blockers, CI and code status.
2. [WORK_PROTOCOL.md](WORK_PROTOCOL.md) — required start/work/handoff steps and branching.
3. [MODULE_ROADMAP.md](MODULE_ROADMAP.md) — all modules and dependencies, with honest statuses.
4. [DECISIONS.md](DECISIONS.md) — approved technical ADRs and open legal/accountant gates.
5. [BUSINESS_RULES.md](BUSINESS_RULES.md) — decisions from business discovery.
6. [SCHEMA_FOUNDATION.md](SCHEMA_FOUNDATION.md) and [EXECUTION_PLAN.md](EXECUTION_PLAN.md).
7. [CI_STATUS.md](CI_STATUS.md) and latest [handoff](handoffs/P0-01.md) for current issue.
8. Both repositories' `AGENTS.md`, `README.md`, actual code and latest GitHub CI/PR state.

**If documentation and live repository/CI disagree, investigate before coding, update the documentation, and log the discrepancy.** Do not assume previously described work is verified.

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

## Latest owner authorization — ADR-021 (2026-11-16)

Owner explicitly authorized integration-only squash merges of Backend PR #6 and Frontend PR #2 into their respective `rebuild/erp-v2` branches, accepting responsibility for non-production project review. This is NOT a formal independent security review, does NOT complete SEC-01..SEC-08, and does NOT authorize real privileged user onboarding, production/staging deployment, `main`/`oberation` changes, key rotation or V1 data migration. Owner approval is for the development integration action only; security production gates remain.

Check live GitHub PR/base SHAs and newest Project Status/Handoff for actual merge result. Historical 'no merge' text below applies to older state and is superseded ONLY for these development integrations.

## Current active task and cross-chat handoff (2026-11-16)

**Active:** P0-02 — Auth/MFA/Company RBAC, [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4), [latest handoff](handoffs/P0-02.md), backend [Draft PR #6](https://github.com/Elmo-Tech/erp-backend/pull/6), frontend [Draft PR #2](https://github.com/Elmo-Tech/erp-frontend/pull/2). All **Draft and UNMERGED**.

- **Approved security policy principles:** [ADR-020](DECISIONS.md) — mandatory privileged MFA, verified trusted activation, audited recovery/rebind, Egypt/Italy company isolation and explicit consolidation reporting, safe key lifecycle, first-party secure sessions. This is **NOT merge approval** or approval of production configuration.
- **Verified technical evidence:** backend atomic MFA [PostgreSQL CI #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049) PASS (27 tests/233 assertions); [Frontend CI #43885394399](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394399) PASS; [Chromium #43885394390](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43885394390) PASS against pinned atomic backend SHA `ceded61c936009105b0607c022c02967e2645a6b`. These results predate any docs-only approval commit's new CI run.
- **Still pending:** named trusted activation approver(s), independent qualified human security review, detailed recovery/rebind runbook, exact RBAC matrix, production key/session/CORS decisions, reviewer issue resolution and separate explicit merge/deploy authorization.
- **P0-01:** [Issue #1](https://github.com/Elmo-Tech/erp-backend/issues/1) remains open for acceptance of browser evidence in the appropriate integration baseline. Do not auto-close.
- **Do not start P0-03.** New chats should continue P0-02 from the latest task branch and use [NEW_CHAT_PROMPT_P0-02.md](NEW_CHAT_PROMPT_P0-02.md); do not restart the scaffold.

## Rule for each new chat

**ONE task/Issue → ONE chat → dedicated topic branch(es) → tested PR(s) → written handoff.**
The chat may end before the task is complete; its status must still be updated honestly. Work done solely in chat is NOT project state. Give the next chat links to updated files, PRs and the Issue.

## Manual ChatGPT Project setup

The repo contents are not guaranteed to be auto-loaded into every chat. In the **Elmo ERP ChatGPT Project**, put the concise rules from [NEW_CHAT_PROMPT.md](NEW_CHAT_PROMPT.md) in Project Instructions (user action) and use the relevant Issue in each new chat. Chat naming is a user-side action; the assistant should suggest the exact name.

## Security / approvals

- No secrets, customer/employee financial details or credentials in docs.
- No commits to `main` / `oberation`, no force-push, no production deployments or financial data migrations without separate review.
- Egypt and Italy have separate legal ledgers; development task boundaries must not break that.
