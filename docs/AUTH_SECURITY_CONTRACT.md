# P0-02 — Internal Auth / MFA / Company Permissions Contract

**Task:** [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4). **Status:** implementation under review; granular permission matrix **PROPOSED pending owner security-policy approval**. P0-01 Issue #1 remains open pending acceptance of branch-based browser E2E evidence, not because browser CI is missing.

## Session / trust boundary
- Internal first-party Sanctum SPA cookie sessions only (Laravel web guard, `auth:sanctum` with stateful SPA middleware). No browser bearer/localStorage tokens.
- Call `GET /sanctum/csrf-cookie` before state-changing requests and send cookie credentials plus `X-XSRF-TOKEN`; environment-specific exact SPA origins are an allowlist, never `*`.
- Use a common site/domain for SPA/API; localhost ports 5173/8000 are test-only. Cross-site hosting requires separately reviewed `SameSite=None; Secure` and a compatible HTTPS cookie-domain policy.
- `POST /api/v1/auth/login`: generic bad-credential response, IP+email limiter, regenerate session. Returns 200 authenticated user or 202 pending MFA; no account password recovery/registration in this task.
- `GET /api/v1/auth/mfa/status`, `POST /api/v1/auth/mfa/enroll/start`, `POST /api/v1/auth/mfa/enroll/confirm`, `POST /api/v1/auth/mfa/verify`: password-verified pending challenge for at most five minutes. MFA challenge must finish BEFORE authenticated session. TOTP six digits / 30 seconds / ±one step, consumed monotonically; eight hashed single-use recovery codes shown once. Privileged enrollment has **no access bypass**. Initial password-only enrollment ownership policy remains **PROPOSED** (approval required before production).
- `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`: must have authenticated and active session; invalidated on logout; server-enforced session idle lifetime.
- `GET /api/v1/companies`, `POST /api/v1/companies/switch` (body `company_id` UUID), `GET /api/v1/companies/context`, `GET /api/v1/companies/authorize/{area}` (authorization-only probe, no business data).
- Company context exists **only** in the server session and must be revalidated on every request; client headers, URLs and body values cannot switch company implicitly. Revocation immediately denies company access. Existing session forced to reauthenticate if privileged MFA was not completed.

## Proposed role → permission matrix (not legal / payroll approval)

| Grant scope | Role key | company.view | projects.view | finance.view | finance.export | payroll.view |
|---|---|---|---|---|---|---|
| Global | `central_management` | all active companies | no | no | no | no |
| Company | `company_manager` | yes | yes | yes | no | no |
| Company | `project_manager`, `project_member` | yes | yes | no | no | no |
| Company | `finance` | yes | no | yes | yes | no |
| Company | `hr` | yes | no | no | no | yes |
| Company | `external_accountant` | yes | no | yes | yes | no |
| Company | `viewer` | yes | no | no | no | no |

- Global management enables an all-company company *overview and switcher* only. Financial and payroll checks still require the appropriate **same-company** explicit role; no cross-ledger implicit grant.
- A user may have multiple company memberships; all grants derive from `active` membership and `active` company status. Explicit active global grant uses `global_role_assignments`. Unknown roles/areas deny by default.
- Never infer finance/payroll rights from assignments to projects or consolidated reporting.
- Every downstream API, queue job, policy and export MUST call the same service and pass an explicit reviewed legal company ID. This task exposes **no ledger/payroll data**.
- No public signup or admin provisioning API. Trusted internal provisioning is an operational prerequisite; role assignment UI and invitation flows are separate gated work.

## Testing & release gate
- PHPUnit on disposable PostgreSQL 18 validates guest denial, session, MFA including recovery reuse, company switching and revoked memberships, account-disabled denial, role-negative matrix and cross-company access.
- Real browser with isolated Vite origin and Laravel origin must prove cookies/CSRF/CORS (positive and negative); test origin and screenshots/logs without secrets must be linked in the handoff.
- Treat lack of browser evidence as **NOT VERIFIED**, not as CI success.
- PR review and policy approval required before merge, and separate production rollout approval needed for any real accounts.

## Test evidence and explicit limitations

- Browser E2E verified in isolated GitHub Actions with Chromium, Vite localhost:5173, Laravel localhost:8000, PostgreSQL 18 and untrusted localhost:5999 origin: **PASS** https://github.com/Elmo-Tech/erp-frontend/actions/runs/43868982519.
- Frontend typecheck/Vitest/build/Docker CI **PASS** https://github.com/Elmo-Tech/erp-frontend/actions/runs/43868982516.
- Backend authentication, migration, company negative tests and Docker **PASS** https://github.com/Elmo-Tech/erp-backend/actions/runs/43868872071; latest security remediation passed PostgreSQL 18 and 24 PHPUnit tests / 178 assertions, see run #43874999124.
- The MFA bootstrap/enrollment policy and role matrix remain **PROPOSED**. No reviewed policy approval, no production browser or real organization account tests.

## Post-review security implementation (PROPOSED, not approved rollout policy)

**Encrypted storage.** `User::mfa_secret` is an Eloquent `encrypted` cast and omitted, along with `mfa_last_step`, from `User::toArray()`/`toJson()`. Raw `users.mfa_secret` database value is Laravel ciphertext, not the TOTP seed. The pending `erp.enrollment_secret` is separately encrypted via `Crypt::encryptString` before session storage and decrypts only at confirmation (including when `SESSION_ENCRYPT=false`). A setup seed is **intentionally returned only by `POST /auth/mfa/enroll/start`** to pair an authenticator. Auth API responses use private/no-store cache policy and do not serialize the saved seed. Recovery codes are digests at rest and shown once in confirm response. No secrets in handoff, logs or CI summaries.

**Activation.** For first privileged enrollment, `user_mfa_enrollment_authorizations` must have an unconsumed, future-expiring entry with an internal non-PII `approved_reference`. The API does not issue these authorizations; trusted human identity/approval, issuance, audit and rebind/recovery procedure need an explicit policy decision. Expiration/revocation rejects enrollment; grant consumed on successful confirmation. Challenge after password lasts five minutes. No password-only MFA enrollment bypass.

**Limits.** Login: per-IP 20 failures / 300s and per-account + per-(IP,account) 5 failures / 300s. MFA: per-account + per-challenge 5 failures / 300s and per-IP 30 / 300s; enrollment start 3 / 300s. Correct credentials do not bypass an existing lock. Cache-store persistence, operational unlock/escalation and potential denial-of-service considerations require security approval.

**Consolidation.** `central_management` grants selection/overview of all active companies, not finance/payroll details. A separate active `consolidation_viewer` global grant plus central role is required to authorize **future** `consolidation.view` requests. `GET /api/v1/companies/consolidation/access` is a boolean probe only (no financial data). Correct intercompany elimination/report computations belong to M16; not implemented by P0-02. Actual finance/payroll access requires explicit same-company role regardless of central report permissions.

**Key rotation.** APP_KEY is the encryption root for existing TOTP seeds. Before production: define secure key escrow, staged compatibility/old-key read strategy, audited re-encryption, restore drills and rollback; never change encryption keys without this reviewed migration. **No key rotation was executed.**

**Status:** all above controls remain in DRAFT PR #6/#2. Exact grant issuance and recovery policy, per-role permissions, production cookie origins, audit/readiness and deployment are **PROPOSED / NOT APPROVED**.

**Security review E2E evidence:** [Chromium #43875272279](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43875272279) **PASS** on frontend `6b2b3f97c2dbe8321373cd7d4b195b95ed5f5869` with exact pinned backend `0d2843ccb2df9c5df3629ce3f4ad0b56dfd44cea`. New assertions include trusted first activation in Chromium, invalid/expired TOTP, single-use recovery, DOM redaction and no-store headers. [Backend #43874999124](https://github.com/Elmo-Tech/erp-backend/actions/runs/43874999124) PASS 24/178 and PostgreSQL/Docker; [frontend #43875272312](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43875272312) PASS. Policy is still **PROPOSED**.

## Atomic MFA enrollment persistence (2026-11-16, implemented — review required)

Review [PR #6 finding #6918381867](https://github.com/Elmo-Tech/erp-backend/pull/6#issuecomment-6918381867). The complete grant consumption, saved Eloquent-encrypted `users.mfa_secret`/`mfa_last_step`, and all eight recovery digests must commit **together**. `AuthController::enrollConfirm` locks the user row (`lockForUpdate`), refuses disabled/already-enrolled accounts, and uses an unconsumed, unexpired conditional grant update inside `DB::transaction`; the service's recovery rotation has its own transaction and bulk insert, composed within the outer transaction. On DB failure, no factor/recovery/grant change persists, the pending encrypted session seed remains for retry, and the server sends generic 503 without authenticating; only sanitized exception class (not exception object/SQL or recovery secrets) may be logged.

**Verified PostgreSQL failure injection:** [CI #43881203049](https://github.com/Elmo-Tech/erp-backend/actions/runs/43881203049) **PASS** for all 27 tests / 233 assertions, including failed user factor write, recovery-digest insert, standalone rotation, rollback, and successful retry. This does **not** prove multi-process production concurrency behavior or constitute an external human security audit; request independent review.

**Browser SHA warning:** The previously successful [Chromium #43876191068](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43876191068) checks out immutable backend `0d2843ccb2df9c5df3629ce3f4ad0b56dfd44cea`, which predates atomic commits. To validate current code, update both frontend E2E workflow SHA occurrences to `ceded61c936009105b0607c022c02967e2645a6b`, then run CI and record the result. This update was blocked by the connected GitHub write tool at handoff, so E2E for new code is **NOT VERIFIED**.

All first-activation issuance, recovery/rebind, RBAC/consolidation, key rotation and staging/prod security controls remain **PROPOSED** pending owner and independent reviewer approval; no merge or release authorized.
