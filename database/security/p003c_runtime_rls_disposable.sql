-- Owner-run, rollback-only PostgreSQL 18 experiment. RLS never persists.
-- GUC bypass is EXPECTED and must remain a release-blocking finding.
\set ON_ERROR_STOP on
SELECT CASE WHEN current_database()='elmo_erp_test' AND current_user='elmo'
  THEN 'true' ELSE 'false' END AS safe \gset
\if :safe
\else
  \echo 'REFUSED: disposable owner only'
  \quit 3
\endif
BEGIN;
INSERT INTO fiscal_years (id, company_id, label, starts_on, ends_on, state, created_at, updated_at)
VALUES
('00000000-0000-4000-8000-0000000000d1','00000000-0000-4000-8000-0000000000c1','P003C-EG','2026-01-01','2026-12-31','open',now(),now()),
('00000000-0000-4000-8000-0000000000d2','00000000-0000-4000-8000-0000000000c2','P003C-IT','2026-01-01','2026-12-31','open',now(),now());
ALTER TABLE fiscal_years ENABLE ROW LEVEL SECURITY;
GRANT SELECT ON fiscal_years TO p003c_runtime;
CREATE POLICY p003c_runtime_fiscal_probe ON fiscal_years FOR SELECT TO p003c_runtime
  USING (company_id = NULLIF(current_setting('app.company_id', true), '')::uuid);
SAVEPOINT eg_context;
SET LOCAL ROLE p003c_runtime;
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE id IN
 ('00000000-0000-4000-8000-0000000000d1','00000000-0000-4000-8000-0000000000d2'))=0
 THEN 1 ELSE 0 END AS no_context_sees_nothing;
SELECT set_config('app.company_id','00000000-0000-4000-8000-0000000000c1',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE id='00000000-0000-4000-8000-0000000000d1')=1
 AND (SELECT count(*) FROM fiscal_years WHERE id='00000000-0000-4000-8000-0000000000d2')=0
 THEN 1 ELSE 0 END AS eg_scoped_policy;
-- Intentional negative: arbitrary writable custom GUC defeats this RLS policy.
SELECT set_config('app.company_id','00000000-0000-4000-8000-0000000000c2',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE id='00000000-0000-4000-8000-0000000000d2')=1
 THEN 1 ELSE 0 END AS vulnerable_guc_bypass_reproduced;
ROLLBACK TO SAVEPOINT eg_context;
SAVEPOINT it_context;
SET LOCAL ROLE p003c_runtime;
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE id IN
 ('00000000-0000-4000-8000-0000000000d1','00000000-0000-4000-8000-0000000000d2'))=0
 THEN 1 ELSE 0 END AS no_leak_between_transactions;
SELECT set_config('app.company_id','00000000-0000-4000-8000-0000000000c2',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM fiscal_years WHERE id='00000000-0000-4000-8000-0000000000d2')=1
 AND (SELECT count(*) FROM fiscal_years WHERE id='00000000-0000-4000-8000-0000000000d1')=0
 THEN 1 ELSE 0 END AS it_scoped_policy;
ROLLBACK TO SAVEPOINT it_context;
ROLLBACK;
SELECT 1 / CASE WHEN NOT (SELECT relrowsecurity FROM pg_class WHERE oid='public.fiscal_years'::regclass)
 AND NOT EXISTS (SELECT 1 FROM pg_policies WHERE schemaname='public' AND policyname='p003c_runtime_fiscal_probe')
 AND NOT has_table_privilege('p003c_runtime','fiscal_years','SELECT')
 THEN 1 ELSE 0 END AS no_permanent_rls_changes;
\echo 'PASS: disposable RLS policy only. FINDING: writable-GUC tenant bypass demonstrated.'
