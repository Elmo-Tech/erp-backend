-- P0-03E: disposable PostgreSQL 18 permission partition ONLY; never run on production.
-- These SQL principals are a target, not wired Laravel connections or a trusted issuer.
\set ON_ERROR_STOP on
SELECT CASE WHEN current_database()='elmo_erp_test' AND current_user='elmo'
 THEN 'true' ELSE 'false' END AS safe \gset
\if :safe
\else
 \echo 'REFUSED: disposable migration-owner connection required'
 \quit 3
\endif
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOINHERIT NOBYPASSRLS', role_name, role_password)
FROM (VALUES
 ('p003e_web', :'web_password'),
 ('p003e_mfa', :'mfa_password'),
 ('p003e_queue', :'queue_password'),
 ('p003e_session', :'session_password')
) AS identities(role_name, role_password)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname=role_name) \gexec
CREATE ROLE p003e_admin NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOINHERIT NOBYPASSRLS;
GRANT CONNECT ON DATABASE elmo_erp_test TO p003e_web, p003e_mfa, p003e_queue, p003e_session;
GRANT USAGE ON SCHEMA public TO p003e_web, p003e_mfa, p003e_queue, p003e_session;
-- Web needs hash for existing Hash::check; no MFA secret and no SELECT * users.
GRANT SELECT (id, name, email, password, remember_token, email_verified_at, is_active,
 created_at, updated_at) ON public.users TO p003e_web;
GRANT SELECT ON public.companies, public.company_memberships,
 public.company_role_assignments, public.global_role_assignments TO p003e_web;
-- MFA SQL credentials must not be available to arbitrary SQL on the Web connection.
-- Enrollment/verification permissions below REQUIRE separate authenticated dispatch.
GRANT SELECT (id, is_active, mfa_secret, mfa_last_step) ON public.users TO p003e_mfa;
GRANT UPDATE (mfa_secret, mfa_last_step, updated_at) ON public.users TO p003e_mfa;
GRANT SELECT ON public.user_mfa_enrollment_authorizations TO p003e_mfa;
GRANT UPDATE (consumed_at, updated_at) ON public.user_mfa_enrollment_authorizations TO p003e_mfa;
GRANT SELECT, INSERT, DELETE ON public.user_mfa_recovery_codes TO p003e_mfa;
-- Queue infrastructure only, not company context/domain tables.
GRANT SELECT, INSERT, UPDATE, DELETE ON public.jobs, public.failed_jobs TO p003e_queue;
GRANT USAGE ON SEQUENCE public.jobs_id_seq, public.failed_jobs_id_seq TO p003e_queue;
-- Cookie sessions need a separate broker/connection, not the Web SQL principal.
GRANT SELECT, INSERT, UPDATE, DELETE ON public.sessions TO p003e_session;
-- Admin stays non-login and has NO DML: SEC-01/02 trusted approval remains unresolved.
SELECT 1 / CASE WHEN
 NOT (SELECT rolcanlogin FROM pg_roles WHERE rolname='p003e_admin')
 AND NOT EXISTS (SELECT 1 FROM pg_auth_members m JOIN pg_roles r ON r.oid=m.roleid
  WHERE r.rolname='p003e_admin')
 AND NOT has_table_privilege('p003e_admin','company_memberships','UPDATE')
 AND NOT has_table_privilege('p003e_admin','user_mfa_enrollment_authorizations','INSERT')
 AND NOT has_column_privilege('p003e_web','users','mfa_secret','SELECT')
 AND NOT has_column_privilege('p003e_web','users','updated_at','UPDATE')
 AND NOT has_table_privilege('p003e_queue','users','SELECT')
 AND NOT has_table_privilege('p003e_mfa','sessions','DELETE')
 THEN 1 ELSE 0 END AS strictly_separated_ci_roles;
\echo 'PASS: provisional isolated principals created; app workflow compatibility not proved'