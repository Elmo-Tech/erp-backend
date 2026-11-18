# Elmo Tech ERP V2 — Start Here

## Active financial safety review checkpoint — 2026-11-18

- **P0-04A [Draft PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14) CI false-positive was repaired** on code SHA `4543a39652f300f388c5f0aa8e0b4fe0e3e0a7a2`: [exact-head full #44346741810 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44346741810). The standalone P0-03 populated migration fixture now fails with exit code 1 on an assertion, has an intentional negative selftest, strict stage PASS markers, and a two-migration `--step=2` rollback for the A-only branch. P0-04A trigger `23514` and independent composite-FK `23503` are both tested. The old #44308817619 result was **not** sufficient for this regression.
- P0-04A #14 / B #16 / FX #18 remain **Draft, open, unmerged**; composition [#20](https://github.com/Elmo-Tech/erp-backend/pull/20) is **NEVER MERGE**. Integration `rebuild/erp-v2` remains unchanged. [Review Issue #19](https://github.com/Elmo-Tech/erp-backend/issues/19) / [Parent #13](https://github.com/Elmo-Tech/erp-backend/issues/13) track remaining real runtime ACL, independent human review, posting lock protocol and future authorization/idempotency/audit; **development merge NO-GO** until owner makes a separate decision.
- This new documentation-only checkpoint requires its **own exact-head CI**; the cited code result applies to `4543a39652f300f388c5f0aa8e0b4fe0e3e0a7a2`.

---

> **Canonical project handoff:** read this page at the beginning of EVERY fresh chat, task, or engineering session.
> Scope: both repositories, not just the backend. **GitHub is the durable source of truth, not conversation memory.**

## Current task checkpoint and next Issue — 2026-11-18 (LATEST)

**Full engineering CI verified:** [Draft PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14) pre-handoff HEAD `8c0565175381b543b08896f04893fe2dc50c0e63` completed [**GitHub Actions #44306006459 SUCCESS**](https://github.com/Elmo-Tech/erp-backend/actions/runs/44306006459) on the exact SHA. All workflow steps succeeded including PostgreSQL 18 migrations/PHPUnit/P004 guards, nonempty P0-03 EG/IT upgrade/rollback, two-client direct-PG18 and PgBouncer journal-lock negative tests, Auth/MFA/Session/Queue regressions, Docker image and API health. New code `7c259d4c345c1892bf9f8a3598589c067246f535` independently passed [#44305404543](https://github.com/Elmo-Tech/erp-backend/actions/runs/44305404543). The next *documentation-only* SHA requires its own distinct CI conclusion.

**Scope remains P0-04A ONLY:** fail-closed new posting/reversal SQL; historical posted/reversed header+line immutable in row-level triggers; **no posting command, public GL API, idempotent source ledger, verified finance authorization or real data**. PR #14 DRAFT/OPEN/UNMERGED; [Issue #13](https://github.com/Elmo-Tech/erp-backend/issues/13) remains OPEN. No owner merger permission has been received for this PR. **AI source review is NOT an independent human security/accounting review**.

**Follow-up findings and next bounded task:** [new Issue #15 — P0-04B exact decimal internal-only posting eligibility](https://github.com/Elmo-Tech/erp-backend/issues/15). The existing `JournalDraftValidator` sums PHP integers without total overflow protection: 1,000 debit lines of `999999999999.0000` and 1,000 corresponding credits with one `999999999998.9999` produce real debit-credit difference `0.0001`, but PHP 8.4 accumulation overflows into float and yields falsely equal aggregates. **Mathematical/implementation defect reproduced locally**, not a report of actual posted money. Separate read-only validation fix and exact PostgreSQL NUMERIC aggregation are required; reject on any overflow/precision uncertainty. P0-04A row-trigger lock-order proof covers parent-held scenarios but NOT future posted-command-vs-child-update: line tuples may be locked before line BEFORE ROW trigger obtains parent; never introduce parent-then-line `FOR UPDATE` without adversarial deadlock test. Also deny `TRUNCATE`/DDL/trigger-disable to runtime roles—row triggers don't block `TRUNCATE` and existing CI uses disposable privileged roles.

**Next action:** keep P0-04A Draft pending qualified source review and explicit separate owner merge instruction; implement Issue #15 in a small independent task branch from `rebuild/erp-v2`, *no posting transition or new API*, and prove overflow/period/account/EG↔IT and PgBouncer negative tests. Lock/period/account scope and F03 audit/idempotency/outbox remain pre-Posting gates. ADR-024 stays PROPOSED; SEC #7, P0-03 residual #8 and MFA broker #11 OPEN; PR #10 exploratory/unmerged. NO merge/deploy/production/real financial rows/`main`/`oberation` changes.

---

## Latest engineering state — 2026-11-17

- **P0-03 development integration FINISHED:** Backend PR #12 squash-merged to `rebuild/erp-v2` as `21a085f5b101126cb6f535fdb03f204cb99d0405`; [post-merge CI #44115915862 SUCCESS](https://github.com/Elmo-Tech/erp-backend/actions/runs/44115915862).
- **P0-04/F02 actively building (non-production):** [Issue #13](https://github.com/Elmo-Tech/erp-backend/issues/13), [Draft PR #14](https://github.com/Elmo-Tech/erp-backend/pull/14) on `task/p0-04-ledger-posting`. Initial P0-04A immutable journal guard [code commit `f880d7c`](https://github.com/Elmo-Tech/erp-backend/commit/f880d7ce8d6e82dd12d0140a3548bab4d1f140f8) passed [full CI #44119384809](https://github.com/Elmo-Tech/erp-backend/actions/runs/44119384809) including PG18, PHPUnit, P0-03 migration rollback/PgBouncer, Auth/MFA, Docker/API health. A later docs-only commit needs its own CI.
- **P0-04A safety boundary ONLY:** direct posted/reversed journal writes disabled until reviewed posting service; historic posted headers/lines immutable. This is **not** balanced posting, reversal, idempotency, audit/outbox or a permitted financial API.
- **Security/production NOT approved:** exploratory P0-03 PR #10 remains draft/unmerged; SEC-01..08 [Issue #7](https://github.com/Elmo-Tech/erp-backend/issues/7), P0-03 residual [#8](https://github.com/Elmo-Tech/erp-backend/issues/8) and MFA broker/lock [#11](https://github.com/Elmo-Tech/erp-backend/issues/11) OPEN; ADR-022/023/024 PROPOSED. No deployment/real user MFA/financial posting, no permanent RLS, no `main`/`oberation` changes.
- **Read [P0-04 Handoff](handoffs/P0-04.md) and live GitHub PR/CI before continuing.** Earlier 'P0-02 active' text farther below is a historical pre-merge snapshot superseded by this section.

---

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
