-- P0-03C: CI fixture only. Never run against staging/production.
-- Owner/migration user is elmo; runtime user is a distinct NOINHERIT/NOBYPASSRLS LOGIN.
\set ON_ERROR_STOP on
SELECT CASE WHEN current_database()='elmo_erp_test' AND current_user='elmo'
  THEN 'true' ELSE 'false' END AS safe \gset
\if :safe
\else
  \echo 'REFUSED: disposable elmo_erp_test and migration owner elmo required'
  \quit 3
\endif
SELECT format('CREATE ROLE p003c_runtime LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOINHERIT NOBYPASSRLS', :'runtime_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='p003c_runtime') \gexec
-- Only public authentication/tenant selectors are readable; no financial tables.
GRANT CONNECT ON DATABASE elmo_erp_test TO p003c_runtime;
GRANT USAGE ON SCHEMA public TO p003c_runtime;
GRANT SELECT ON users, companies, company_memberships, company_role_assignments,
  global_role_assignments TO p003c_runtime;
-- SELECT ... FOR UPDATE requires UPDATE privilege in PostgreSQL. Limit it to an
-- inert timestamp column, never status, role_key, company_id or is_active.
GRANT UPDATE (updated_at) ON users, companies, company_memberships, company_role_assignments TO p003c_runtime;
-- HTTP cookie sessions and queue infrastructure, not membership/grant administration.
GRANT SELECT, INSERT, UPDATE, DELETE ON sessions, jobs, failed_jobs TO p003c_runtime;
GRANT USAGE, SELECT ON SEQUENCE jobs_id_seq, failed_jobs_id_seq TO p003c_runtime;
-- Keep tenant authorization tables and user identities read-only for runtime.
-- No default ALTER DEFAULT PRIVILEGES: a new table is denied until explicitly reviewed.
SELECT 1 / CASE WHEN
  (SELECT NOT rolsuper AND NOT rolbypassrls AND NOT rolcreaterole AND NOT rolcreatedb AND NOT rolinherit
   FROM pg_roles WHERE rolname='p003c_runtime')
  AND NOT pg_has_role('p003c_runtime','elmo','MEMBER')
  AND NOT has_schema_privilege('p003c_runtime','public','CREATE')
  AND NOT has_table_privilege('p003c_runtime','fiscal_years','SELECT')
  AND NOT has_column_privilege('p003c_runtime','company_memberships','status','UPDATE')
  AND has_table_privilege('p003c_runtime','company_memberships','SELECT')
  THEN 1 ELSE 0 END AS runtime_grant_matrix;
\echo 'PASS: separate restricted runtime identity provisioned only in disposable CI'
