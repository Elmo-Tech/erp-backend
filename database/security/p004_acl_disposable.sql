\set ON_ERROR_STOP on
-- Disposable P0-04-ACL proof only. Execute as migration owner on elmo_erp_test.
-- Password is passed via psql -v runtime_password=...; never commit a value.
DO $guard$
BEGIN
  IF current_database() <> 'elmo_erp_test' OR current_user <> 'elmo' THEN
    RAISE EXCEPTION 'P0-04-ACL refuses non-test database or non-owner connection';
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'elmo_runtime') THEN
    RAISE EXCEPTION 'P0-04-ACL runtime role already exists: refuse to reconfigure an existing principal';
  END IF;
END
$guard$;
SELECT format(
  'CREATE ROLE elmo_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS PASSWORD %L',
  :'runtime_password'
) \gexec
-- This is a disposable DATABASE-wide test boundary; explicit grants only.
REVOKE ALL ON DATABASE elmo_erp_test FROM PUBLIC;
GRANT CONNECT ON DATABASE elmo_erp_test TO elmo_runtime;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO elmo_runtime;
-- Historical application schema only: never grant DDL/ownership/TRUNCATE.
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO elmo_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO elmo_runtime;
-- PUBLIC EXECUTE on pre-existing functions is a separate unreviewed risk.
-- Future migrations need a deliberate, audited re-grant: NO default privileges.
SELECT 'PASS: disposable migration owner and runtime LOGIN are distinct; DML-only grants' AS result;
