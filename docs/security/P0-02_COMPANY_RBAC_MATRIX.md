# P0-02 — Egypt/Italy Company RBAC and Consolidation Matrix

**Status:** CODE-RECONCILED PROPOSED MATRIX — exact role mapping requires owner + independent security approval. 2026-11-16.
**Sources:** `app/Domain/Companies/CompanyAuthorization.php`, `app/Domain/Auth/MfaService.php`, `app/Http/Controllers/CompanyController.php` at Backend Draft PR #6; ADR-020 principles already approved.

## Implemented authorization-only behavior (not a production grant approval)

| Grant | Scope | company.view | projects.view | finance.view | finance.export | payroll.view | Both-company overview | consolidation.view |
|---|---|---|---|---|---|---|---|---|
| `central_management` | Global active grant | Yes (any active company) | No | No | No | No | Yes | No alone |
| `company_manager` | Active membership in selected legal company | Yes | Yes | Yes | No | No | No | No |
| `project_manager` | Company | Yes | Yes | No | No | No | No | No |
| `project_member` | Company | Yes | Yes | No | No | No | No | No |
| `finance` | Company | Yes | No | Yes | Yes | No | No | No |
| `hr` | Company | Yes | No | No | No | Yes | No | No |
| `external_accountant` | Company | Yes | No | Yes | Yes | No | No | No |
| `viewer` | Company | Yes | No | No | No | No | No | No |
| `consolidation_viewer` | Global active grant | No standalone grant | No | No | No | No | No | **Yes only together with active `central_management`** |

**Clarification:** `company.view` for global management is an effect of choosing an active company, not a permission to its transactions. `company_manager` currently has `finance.view` in code — this is **a proposed high-impact authorization choice**, not yet owner-approved. Also no automatic `finance.export` or `payroll.view` for that role.

## Isolation and enforcement rules

- Egypt and Italy have separate legal entities, company IDs, GL, payroll, bank, invoices and tax. Every sensitive handler must pass a server-validated company ID and invoke an explicit permission check. A project shared across countries does **not** confer payroll or finance entitlement.
- Ordinary users need **active user + active membership + active legal company**; permissions come only from role assignments associated with that membership and company. Any unaffiliated company selection returns 403 and must not change session context.
- Global `central_management` can list/select both active companies even without same-company membership; all finance/payroll operations still need an explicit active company membership and matching company role.
- User disabled -> 401 for current session; membership revoked -> company selection/authorization denied on subsequent request; role removed -> corresponding permission removed. Existing browser session is NOT a claim of everlasting authorization.
- Unrecognized company role or permission should deny. Avoid permissions derived from UI state, request header, query string, body-supplied company, or shared customer/project ID.
- MFA mandatory for active `central_management` or active-company `company_manager`, `finance`, `hr`, `external_accountant`. `project_manager`, `project_member`, `viewer` only have optional MFA by current policy; any voluntary enrolled factor still needs MFA at login.
- P0-02 only exposes company lists, switching, boolean authorized probes and a central consolidation *access probe*. There are **no real finance, payroll, exports, ledgers, consolidation figures or aggregation endpoints** to approve here.

## Consolidated reports: explicitly separate later M16 delivery

To view any future consolidated **report**, require authenticated current session with MFA where applicable; active `central_management` **and** `consolidation_viewer` global grants, plus a narrowly reviewed report endpoint/service. Do not infer all-company ledger access or use finance detail query privileges from this pair.

**Proposed future report contract requiring approval:** report identifier, period, data classification, purpose and lawful audience; per-company currency and FX treatment; source-ledger provenance, intercompany matching and elimination; aggregate totals only by default; drill-down, employee salary details, bank accounts, customer PII and raw ledger rows excluded unless separately authorized and legally reviewed. Mask small cells and audit report generation/export; enforce legal-company scoping in both SQL queries and background jobs. No report totals can be validated at P0-02.

## Manual approval/review tests

- [ ] Confirm `company_manager` own-company finance.view, and whether export or payroll are explicitly denied.
- [ ] Validate segregation of duties: HR payroll, Finance exports, accountant scope/no payroll, project worker no finance.
- [ ] Approve special-case temporary grants, joiners/movers/leavers, revocation and dual-role union rules.
- [ ] Review all global grants/hidden management pages, including accidental combined roles across Egypt/Italy.
- [ ] M16 data classification and consolidated report scope reviewed independently before any implementation; intercompany elimination must be correct and audit-ready.
- [ ] Re-run explicit allow/deny matrix tests and review real authorization middleware integration before merging.

## Test proof versus limits

Backend RoleBoundariesTest and AuthHardeningTest plus Chromium E2E passed in GitHub Actions on synthetic CI entities; latest PR-head backend CI #43890579327 and frontend Chromium #43890384899 are SUCCESS. This **does not** prove actual finance endpoints or report correctness, because none are implemented in P0-02. Exact matrix still awaits separate explicit owner confirmation.
