# P0-02 — Security Operations Runbooks (Keys, Sessions, Audit)

**Status:** DRAFT/NOT APPROVED FOR PRODUCTION. Owner-approved high-level principles ADR-020. No rotation, session purge, production config change or deployment authorized. 2026-11-16.

## 1. APP_KEY and MFA recovery HMAC — custody and rotation

**Observed:** `User::mfa_secret` is encrypted by Laravel's application encryption key; pending enrollment seed is independently `Crypt::encryptString` encrypted; `MfaService::recoveryHash` uses `hash_hmac('sha256', normalized recovery code, config('app.key'))`. Changing `APP_KEY` impacts **both encrypted TOTP data and recovery-code digest verification**. No approved rotation exists. Production cutover is blocked until this is designed and tested.

**Proposed owner/security-approved rotation runbook (do not execute now):**
1. Owner appoints key custodian and independent security approver; inventory encryption/HMAC consumers, key identifiers, PHP config caches, session/cookie encryption, all nodes/workers and backup locations. Create an auditable change ticket and maintenance rollback decision.
2. Keep keys only in access-restricted managed secret storage (never GitHub/chat/logs); define sealed recovery copy, custody access review and backup restore test. Include MFA-factor availability and session impact in blast-radius analysis; decide if old session cookies are invalidated and require reauthentication.
3. In disposable staging, implement a **versioned migration**: reader recognizes approved prior key for encrypted records while writes use current key, re-encrypt saved TOTP secrets and any other encrypted fields, and either dual-verify versioned recovery HMACs or explicitly revoke/replace recovery codes through a reviewed flow. Laravel `APP_PREVIOUS_KEYS` alone must not be assumed to preserve custom `recoveryHash` digests.
4. Obtain independent approval of rollback compatibility, audit tags, exposure window, monitoring alarms and user notification. Back up before changes and run decryption/verification/restore drills on synthetic data, including recovery-code single-use, old-key rejection after migration, concurrent sessions and multiple workers.
5. During a separately authorized staged production change, rotate key material, redeploy worker config consistently and migrate with idempotent checkpoints; monitor failures without logging secrets. Avoid ever persisting or printing plaintext seed in scripts/logs.
6. Exit only when encrypted-data sample checks, HMAC recovery policy, session invalidation, backups, audit and independent sign-off pass; remove legacy compatibility in a separately reviewed change. Otherwise rollback under approved playbook while retaining ability to decrypt old ciphertext.

**Owner approval required:** key owner/backups/retention, old-key overlap, data migration and recovery-code handling, staging rehearsal and release window. **NOT EXECUTED.**

## 2. Sessions, revocation, lockouts, cookie/CORS

**Observed code:** first-party `auth:sanctum` + `web` session, session regeneration on login and logout invalidation; 5-minute pending MFA challenge; independent account/IP/challenge throttle. `EnsureActiveErpSession` checks active user and privileged MFA state; company selection and permissions are revalidated. `SESSION_DRIVER=redis` is suggested in sample env. Sensitive API responses are no-store. E2E tests are localhost-only.

**Proposed response playbook:**
1. Triage account compromise by account ID and immutable incident ID, not by token/secret. Validate privileged roles and company memberships, currently selected context and last verified MFA without exposing cookie IDs in logs.
2. Suspend targeted user/grants and revoke sessions through a **dedicated globally effective server-side session index/revocation facility** (not present today); do not assume `Auth::logout()` on one browser logs out all devices. Invalidate pending challenges and trusted activation grants. Require fresh password+MFA after restore.
3. Collect only timestamp, event/action, actor, target user ID, company ID, outcome, redacted client/IP risk metadata and correlation ID, with strict retention and access controls. Raise alerts on repeated lockouts, unexpected privilege changes, recovery use and impossible travel (where legally appropriate).
4. Security team approves unlock after risk/identity validation. Avoid manually deleting Redis rate-limit keys as routine bypass; use audited operational controls.
5. Production go-live decision must explicitly define exact SPA/API HTTPS origins, Secure/HttpOnly/SameSite/domain, reverse proxy trusted headers, CSRF/CORS allowlist, idle+absolute timeout and multi-device revocation. Verify with actual staging hosts, cross-subdomain behavior and attempted third-party origin, not only localhost.

**Current gap:** no explicit absolute session timeout, all-devices session revocation or approved incident response/unlock UI was observed in the P0-02 code reviewed; these are review/implementation gates. Framework/default session lifetimes need environment-specific validation. **Do not declare them implemented.**

## 3. Audit integrity, privacy and incidents

1. Define mandatory event taxonomy: trusted activation request/identity verified/independent approve/grant issued/used/revoked/expired; password/MFA success/failure and recovery-code use; admin rebind; role/membership/global grant changes; company switch; consolidation access/export; key/session revocation and incident response.
2. Use authenticated actor, action, company and target IDs, timestamp UTC, ticket/approval reference, result, risk/source class and correlation ID. Log **neither** passwords, TOTP seeds/otpauth URIs, recovery plaintext/digests, auth cookie/session IDs, encryption keys, unredacted document IDs nor raw exception SQL bindings.
3. Deliver to access-controlled append-only/tamper-evident sink with retention, privileged access monitoring and anti-deletion policy; periodic reconciliations link ticket ↔ approval ↔ grant ↔ enrollment and double-check that two distinct accountable humans signed off.
4. Document legal privacy and retention requirements for Egypt/Italy and employee data before collection, plus disaster recovery chain of custody and notification escalation.
5. Security incident reviews audit gaps or repeated failures and freezes grant issuance if provenance is missing; test alerts and restore/read-only audit retrieval. Sample denied access, cross-company attempts and expiration failures in staging.

**Current gap:** P0-02 has sanitized enrollment persistence warning but **not** a complete immutable audit event chain or two-person workflow. This is a proposed operational design; the foundation F03 audit/outbox capability remains NOT STARTED. Neither code nor CI establishes production-grade audit integrity.

## Release readiness check

- [ ] Named accountable owner, key custodian, separate approver, recovery operator, audit reviewer
- [ ] Secrets manager/key inventory and staged rollback / recovery-code compatibility
- [ ] Backup/restore tests and approved rotation / session invalidation strategy
- [ ] Production-origin/HTTPS/cookie/idle+absolute timeout and CSRF/CORS decision
- [ ] MFA reset, global session revocation, alert routing, tamper-evident logging and retention
- [ ] Independent human security review and explicit **separate** merge and deploy approvals

No runbook action affecting real accounts is authorized by this document.
