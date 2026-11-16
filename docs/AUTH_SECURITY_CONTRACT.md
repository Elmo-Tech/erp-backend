# P0-02 — Internal Auth / MFA / Company Permissions Contract

**Task:** [Issue #4](https://github.com/Elmo-Tech/erp-backend/issues/4). **Status:** implementation under review; granular permission matrix **PROPOSED pending owner security-policy approval**. P0-01 remains open for browser E2E.

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
- Backend authentication, migration, company negative tests and Docker **PASS** https://github.com/Elmo-Tech/erp-backend/actions/runs/43868872071; newest security-test CI still must be checked.
- The MFA bootstrap/enrollment policy and role matrix remain **PROPOSED**. No reviewed policy approval, no production browser or real organization account tests.
