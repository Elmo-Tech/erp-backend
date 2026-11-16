# P0-02 — Trusted MFA Activation and Account Recovery Runbook

**Status:** DRAFT OPERATIONAL PROCEDURE / NOT APPROVED FOR REAL ACCOUNTS (2026-11-16)
**Authority:** ADR-020 approves the principles, not the people, verification method, command sequence, production rollout or administrative implementation. Issue #4 / Draft Backend PR #6. No merge, deployment or P0-03.

## Separation of duties (RACI; names must be assigned before use)

| Action | Requester / subject | Identity verifier (V) | Independent security approver (A) | Authorized issuance executor (E) | Security auditor (R) |
|---|---|---|---|---|---|
| First privileged MFA activation | Requests from an existing provisioned account | Verifies employee identity using approved HR/company channels | Approves necessity, role, verified subject and expiry | Creates only the approved expiring single-use grant through an audited administrative workflow | Reconciles grant, usage, denial and expiry |
| Lost authenticator with valid recovery code | Account owner | Self-service recovery code possession is checked by existing challenge; no operator bypass | Not needed for a valid unused code under ADR-020, subject to account risk policy | No issuance; existing single-use code consumed atomically | Reviews suspicious frequency and alerts |
| Lost device AND no recovery code | Account owner via trusted support | Independently verifies identity out-of-band | A different qualified person approves reset/rebind | Authorized administrator uses a **future, reviewed audit-backed reset workflow** | Independently reviews full before/after audit trail |
| Grant revocation / suspected compromise | Owner / Security | Verifies incident and identity as required | Security lead authorizes incident action | Authorized operator revokes pending grants, sessions and factors through **approved** tooling | Reviews and closes incident |

**Hard separation:** V/A/E must not become an unchecked one-person self-approval workflow; A must differ from E and from the affected user. No named individuals have yet been confirmed, hence **DO NOT OPERATE**. A security approver cannot approve their own MFA reset or bootstrap. Emergency access requires a separate pre-authorized break-glass policy and retrospective review, not an MFA bypass.

## Approved evidence rules — detailed method still awaiting owner approval

1. Open an internal, access-restricted ticket with immutable tracking ID and unique user ID, requested legal-company roles and reason. Do not put the user's document numbers, TOTP seed, recovery codes or password into GitHub issues, chat, application logs or tickets.
2. Verify identity through **two independently controlled company-approved channels** (proposed: pre-registered HR directory identity + a real-time callback/video with the manager using known directory contact; not details supplied in the request). Flag executive/admin, role elevation, newly changed contact details, contractor status and unusual location for security escalation. **These methods are proposals**, not authorized collection instructions.
3. Record verifier ID, method class (no identity-document content), UTC time, ticket reference, and result in a restricted audit system with approved retention. Deny if evidence is weak or conflicted.
4. A independently checks company membership, role, case, verification, and a bounded expiry; records signed/attributed decision. No self-approval or password-only action.
5. E verifies grant is not already consumed/revoked and issues only via an audited admin service with dual-approval checks; notify the subject out-of-band. Do not use manual production SQL edits. In disposable CI only, tests directly seed a synthetic grant.
6. Subject logs in using password (five-minute pending challenge), completes trusted enrollment using the one-time authorization, records authenticator, and saves eight one-time recovery codes privately. No screenshot/storage of QR/seed by support.
7. Confirm grant consumed, successful enrollment and expected one-time recovery code count using **non-secret** audit metadata. Unused grant expires automatically; explicit revocation and reissue require new case. Reconcile daily and investigate repeated failed attempts.

## Current implementation versus missing enforceable controls

- Implemented on task branch: `user_mfa_enrollment_authorizations` with `user_id` primary key, `approved_reference`, `expires_at`, `consumed_at`; checked before start and confirmed transactionally alongside encrypted factor and recovery hashes. Only a synthetic test fixture issues a grant in CI. No public grant issuer.
- **NOT IMPLEMENTED:** managed issuance/revocation API or CLI with two-person authority, named actor IDs and reason, immutable grant audit, reliable notification, admin-driven factors reset, evidence retention policy. The existing single-row grant does NOT prove two different humans approved anything. A reference string alone is not an auditable approval chain.
- Proposed initial grant TTL: **10 minutes**, separate five-minute password-MFA challenge; verify and approve these values before real use. Database currently enforces only expiry `> now()` and one-time consume, not a maximum TTL or second approver.

## Account recovery and rebind paths

**Path A — user has an unused recovery code:** User re-enters pending MFA challenge and submits valid code. Existing `MfaService::verifyAndConsume` atomically deletes matching HMAC digest; reuse is denied. This grants a login, **not** a new enrolled device or account reset. A replacement-device workflow remains gated and should demand an additional verified, limited authorization.

**Path B — device lost, no recovery codes:** Suspend sensitive changes; open security case. V verifies identity using approved independent channels; A independently approves reset; E, only using a future dedicated audited service, must transactionally (1) revoke current TOTP and all recovery codes, (2) invalidate ALL existing sessions and pending challenges, (3) revoke pre-existing activation grants, (4) create a new tightly scoped single-use grant (with approvals bound to case), (5) audit before/after state without secrets, (6) send independent notification. Re-enrollment must use new verified challenge. **NOT IMPLEMENTED — NEVER DIRECTLY RESET IN PRODUCTION using SQL or ad hoc commands.**

**Path C — suspected compromise / insider misuse:** Security incident hold and session revocation; do not recover on mere helpdesk assertion. Preserve access-restricted evidence; escalate to security and HR/legal per policy. Any exception requires a preapproved, independently monitored break-glass process.

## Acceptance and owner decisions

- [ ] Assign named V, A, E, R and backups; enforce non-self-approval and staffing separation.
- [ ] Approve identity proof methods, evidence access/retention and data minimization by country.
- [ ] Approve 10-minute grant TTL, reissue/revocation, alerting and account lockout response.
- [ ] Design/build/test audit-backed admin grant/reset/forced-logout mechanisms; exercise concurrency, fraud and replay negatives.
- [ ] Approve notification channels and verify delivered-notification/incident behavior.
- [ ] Independent human security sign-off and separate merge/deployment permission.

**Not a runnable production runbook:** until the missing audited administration/reset controls and human approvals are delivered, these procedures define a gated design, not permission to enroll real privileged accounts.
