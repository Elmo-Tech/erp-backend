# Project Status — Source of Truth

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
