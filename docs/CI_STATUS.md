# First GitHub Actions verification

The first automatic CI runs on 2026-11-16 completed with **failure** on both repositories. The GitHub API provided job objects but no step details, and downloadable job logs returned BlobNotFound. Thus the exact cause is **unknown**. Do not claim passing CI.

Known engineering limitations:
- A deterministic Composer/npm dependency lockfile has not been committed yet. Create and audit lockfiles on a machine with dependency registry access.
- Test setup, actual migration execution on PostgreSQL 18 and Docker image builds still need validation in CI.
- Financial posting is intentionally disabled until DB guards, transaction service, and integration tests exist.

When runner logs become accessible, inspect them, fix startup or dependency issues, rerun tests, and record successful run URLs before describing P0-01 as verified.

## 2026-11-16 current P0-01 update

Latest integration failures are [backend 43846548484](https://github.com/Elmo-Tech/erp-backend/actions/runs/43846548484) and [frontend 43846673824](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43846673824), not just the first initial failures. Both report 2 inaccessible check-run annotations, no step summaries, and downloadable logs returning `BlobNotFound`; fail in around 3–4 seconds. **Exact pre-step root cause NOT VERIFIED.** GitHub UI annotation inspection / runner policy and repository billing are next diagnosis steps.

Draft PR CI: [backend](https://github.com/Elmo-Tech/erp-backend/actions/runs/43857111861) and [frontend](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857118913), **in_progress at initial check; no pass claimed**.

Workflows now explicitly require checked-in authentic lockfiles and attempt repeatable PostgreSQL 18 migration/PHPUnit or npm typecheck/Vitest/build, with disposable Docker image smoke tests. **No lockfiles committed:** registry/network unavailable; generating synthetic lockfile content is forbidden. Tests and image builds remain **NOT VERIFIED**. See [P0-01 handoff](handoffs/P0-01.md).

## Authenticated CI progress (2026-11-16; later than the above notes)

Authentic locks were generated via temporary GitHub Actions jobs and committed on task branches. One-off bootstrap workflow files have been removed. [Backend lock bootstrap succeeded](https://github.com/Elmo-Tech/erp-backend/actions/runs/43857462035). [Frontend lock bootstrap succeeded with npm 11.21.2](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857627756). Hosted npm 10.9.9 resolver crashed with `edgesOut` while generating the frontend lockfile.

**[Frontend PR CI #43857835260 SUCCESS](https://github.com/Elmo-Tech/erp-frontend/actions/runs/43857835260)**: real npm ci, typecheck, 4 Vitest tests, Vite build, Docker build and Nginx smoke.

**[Backend PR CI #43857654487](https://github.com/Elmo-Tech/erp-backend/actions/runs/43857654487)**: Composer validation/install, PostgreSQL 18 migrations and PHPUnit completed successfully; Docker stage running and health smoke not verified at this write. Older statements that both lockfiles are missing or no tests have run are superseded by these dated results. Overall P0-01 still requires final backend CI, review and actual integration evidence.
