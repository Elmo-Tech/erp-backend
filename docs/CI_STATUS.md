# First GitHub Actions verification

The first automatic CI runs on 2026-11-16 completed with **failure** on both repositories. The GitHub API provided job objects but no step details, and downloadable job logs returned BlobNotFound. Thus the exact cause is **unknown**. Do not claim passing CI.

Known engineering limitations:
- A deterministic Composer/npm dependency lockfile has not been committed yet. Create and audit lockfiles on a machine with dependency registry access.
- Test setup, actual migration execution on PostgreSQL 18 and Docker image builds still need validation in CI.
- Financial posting is intentionally disabled until DB guards, transaction service, and integration tests exist.

When runner logs become accessible, inspect them, fix startup or dependency issues, rerun tests, and record successful run URLs before describing P0-01 as verified.
