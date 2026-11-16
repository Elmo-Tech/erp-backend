-- Run as p003c_runtime via DIRECT PostgreSQL and real PgBouncer.
\set ON_ERROR_STOP on
SELECT 1 / CASE WHEN current_user='p003c_runtime'
 AND current_database()='elmo_erp_test'
 AND NOT (SELECT rolbypassrls OR rolsuper OR rolcreatedb OR rolcreaterole FROM pg_roles WHERE rolname=current_user)
 AND NOT has_schema_privilege(current_user,'public','CREATE')
 AND NOT has_table_privilege(current_user,'fiscal_years','SELECT')
 AND NOT has_column_privilege(current_user,'company_memberships','status','UPDATE')
 AND NOT pg_has_role(current_user,'elmo','MEMBER')
 THEN 1 ELSE 0 END AS restricted_runtime_identity;
DO $plpgsql$
BEGIN
  BEGIN
    EXECUTE 'CREATE TABLE public.p003c_must_not_exist (id int)';
    RAISE EXCEPTION 'runtime unexpectedly created a table';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
    EXECUTE 'ALTER TABLE public.companies DISABLE ROW LEVEL SECURITY';
    RAISE EXCEPTION 'runtime unexpectedly changed RLS';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
    EXECUTE 'SET ROLE elmo';
    RAISE EXCEPTION 'runtime unexpectedly assumed migration owner';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
    EXECUTE 'SELECT count(*) FROM public.fiscal_years';
    RAISE EXCEPTION 'runtime unexpectedly read financial data';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
    EXECUTE 'UPDATE public.company_memberships SET status = status WHERE false';
    RAISE EXCEPTION 'runtime unexpectedly modified legal memberships';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
END
$plpgsql$;
SELECT 1 / CASE WHEN to_regclass('public.p003c_must_not_exist') IS NULL
 THEN 1 ELSE 0 END AS forbidden_ddl_left_no_object;
\echo 'PASS: runtime DDL / owner role / RLS toggle / raw finance / membership UPDATE denied'
