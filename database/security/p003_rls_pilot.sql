-- P0-03 experimental RLS: DISPOSABLE PostgreSQL 18 ONLY.
-- Run as the disposable CI database owner: PGPASSWORD=test-only psql
--   -v ON_ERROR_STOP=1 -h 127.0.0.1 -U elmo -d elmo_erp_test
--   -f database/security/p003_rls_pilot.sql
-- All DDL/data/roles/policies are rolled back. NOT a production migration.
-- A role with no BYPASSRLS is mandatory; owner/superuser bypass is not a test.
\set ON_ERROR_STOP on
BEGIN;
CREATE ROLE p003_rls_probe NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
GRANT USAGE ON SCHEMA public TO p003_rls_probe;
GRANT SELECT ON fiscal_years TO p003_rls_probe;

INSERT INTO currencies (code, name) VALUES ('EGP','Synthetic EGP'), ('EUR','Synthetic EUR');
INSERT INTO companies (id, code, legal_name, country_code, base_currency_code, timezone, status, created_at, updated_at)
VALUES
('00000000-0000-4000-8000-000000000031','RLS-EG','Synthetic Egypt','EG','EGP','UTC','active',now(),now()),
('00000000-0000-4000-8000-000000000032','RLS-IT','Synthetic Italy','IT','EUR','UTC','active',now(),now());
INSERT INTO fiscal_years (id,company_id,label,starts_on,ends_on,state,created_at,updated_at)
VALUES
('00000000-0000-4000-8000-000000000041','00000000-0000-4000-8000-000000000031','FY-2026','2026-01-01','2026-12-31','open',now(),now()),
('00000000-0000-4000-8000-000000000042','00000000-0000-4000-8000-000000000032','FY-2026','2026-01-01','2026-12-31','open',now(),now());

ALTER TABLE fiscal_years ENABLE ROW LEVEL SECURITY;
CREATE POLICY p003_fiscal_select ON fiscal_years FOR SELECT TO p003_rls_probe
USING (company_id = NULLIF(current_setting('app.company_id', true), '')::uuid);

-- Privilege check must pass before invoking SET ROLE. No table privilege for sessions/users.
SELECT 1 / CASE WHEN NOT has_table_privilege('p003_rls_probe','users','SELECT')
    AND NOT has_table_privilege('p003_rls_probe','journal_lines','SELECT')
    AND NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='p003_rls_probe' AND rolbypassrls)
    THEN 1 ELSE 0 END AS limited_role;

-- Same physical session/connection: transaction-local tenant context, then cleanup.
SAVEPOINT p003_context_eg;
SET LOCAL ROLE p003_rls_probe;
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years)=0 THEN 1 ELSE 0 END AS no_context_denied;
SELECT set_config('app.company_id','00000000-0000-4000-8000-000000000031',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years)=1
    AND (SELECT count(*) FROM fiscal_years WHERE company_id='00000000-0000-4000-8000-000000000032')=0
    THEN 1 ELSE 0 END AS egypt_cannot_read_italy;
ROLLBACK TO SAVEPOINT p003_context_eg;

SAVEPOINT p003_context_it;
SET LOCAL ROLE p003_rls_probe;
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years)=0 THEN 1 ELSE 0 END AS context_cleared_after_rollback;
SELECT set_config('app.company_id','00000000-0000-4000-8000-000000000032',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years)=1
    AND (SELECT count(*) FROM fiscal_years WHERE company_id='00000000-0000-4000-8000-000000000031')=0
    THEN 1 ELSE 0 END AS italy_cannot_read_egypt;
ROLLBACK TO SAVEPOINT p003_context_it;

SAVEPOINT p003_context_missing_again;
SET LOCAL ROLE p003_rls_probe;
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years)=0 THEN 1 ELSE 0 END AS worker_context_not_reused;
ROLLBACK TO SAVEPOINT p003_context_missing_again;

-- Negative security proof: the same least-privileged role CAN rewrite an arbitrary custom GUC.
-- This intentionally demonstrates RLS policy bypass if the application trusts the GUC
-- without enforcing membership and company-specific SQL scoping. NOT a passing RLS authorization gate.
SAVEPOINT p003_untrusted_guc;
SET LOCAL ROLE p003_rls_probe;
SELECT set_config('app.company_id','00000000-0000-4000-8000-000000000031',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE company_id='00000000-0000-4000-8000-000000000032')=0
    THEN 1 ELSE 0 END AS initial_it_hidden;
SELECT set_config('app.company_id','00000000-0000-4000-8000-000000000032',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE company_id='00000000-0000-4000-8000-000000000032')=1
    THEN 1 ELSE 0 END AS guc_only_rls_is_bypassable;
ROLLBACK TO SAVEPOINT p003_untrusted_guc;

-- No persistent DB role or policy/config changes; sandbox only.
ROLLBACK;
