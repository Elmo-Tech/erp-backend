-- P0-03E: run as each isolated LOGIN, DIRECT and via real PgBouncer.
-- Synthetic read/write probes only. Writes are inside BEGIN/ROLLBACK.
\set ON_ERROR_STOP on
SELECT 1 / CASE WHEN current_database()='elmo_erp_test' AND current_user=:'expected_role'
 THEN 1 ELSE 0 END AS exact_runtime_principal;
SELECT 1 / CASE WHEN
 NOT has_schema_privilege(current_user,'public','CREATE')
 AND NOT has_table_privilege(current_user,'fiscal_years','SELECT')
 AND NOT pg_has_role(current_user,'elmo','MEMBER')
 AND NOT pg_has_role(current_user,'p003e_admin','MEMBER')
 AND NOT has_column_privilege(current_user,'company_memberships','status','UPDATE')
 AND NOT has_column_privilege(current_user,'users','password','UPDATE')
 AND (CASE current_user
   WHEN 'p003e_web' THEN
      has_column_privilege(current_user,'users','password','SELECT')
      AND NOT has_column_privilege(current_user,'users','remember_token','SELECT')
      AND NOT has_column_privilege(current_user,'users','mfa_secret','SELECT')
      AND NOT has_column_privilege(current_user,'users','updated_at','UPDATE')
      AND NOT has_table_privilege(current_user,'sessions','SELECT')
      AND NOT has_table_privilege(current_user,'jobs','SELECT')
      AND NOT has_table_privilege(current_user,'user_mfa_recovery_codes','DELETE')
   WHEN 'p003e_mfa' THEN
      has_column_privilege(current_user,'users','mfa_secret','SELECT')
      AND has_column_privilege(current_user,'users','mfa_last_step','UPDATE')
      AND NOT has_column_privilege(current_user,'users','updated_at','UPDATE')
      AND NOT has_column_privilege(current_user,'users','password','SELECT')
      AND NOT has_table_privilege(current_user,'jobs','SELECT')
      AND NOT has_table_privilege(current_user,'sessions','SELECT')
   WHEN 'p003e_queue' THEN
      has_table_privilege(current_user,'jobs','UPDATE')
      AND NOT has_table_privilege(current_user,'users','SELECT')
      AND NOT has_table_privilege(current_user,'sessions','SELECT')
      AND NOT has_table_privilege(current_user,'user_mfa_recovery_codes','SELECT')
   WHEN 'p003e_session' THEN
      has_table_privilege(current_user,'sessions','DELETE')
      AND NOT has_table_privilege(current_user,'users','SELECT')
      AND NOT has_table_privilege(current_user,'jobs','SELECT')
   ELSE false END)
 THEN 1 ELSE 0 END AS role_matrix;
DO $proof$
BEGIN
 BEGIN
  EXECUTE 'SET ROLE elmo';
  RAISE EXCEPTION 'owner impersonation permitted';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
  EXECUTE 'CREATE TABLE public.p003e_forbidden(id int)';
  RAISE EXCEPTION 'schema DDL permitted';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
  EXECUTE 'SELECT * FROM public.fiscal_years';
  RAISE EXCEPTION 'finance read permitted';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 IF current_user = 'p003e_web' THEN
  BEGIN
   EXECUTE 'SELECT mfa_secret FROM public.users LIMIT 1';
   RAISE EXCEPTION 'web SQL reads MFA secret';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
   EXECUTE 'SELECT * FROM public.users LIMIT 1';
   RAISE EXCEPTION 'unrestricted Eloquent user SELECT works';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
   EXECUTE 'UPDATE public.users SET updated_at=now() WHERE false';
   RAISE EXCEPTION 'web timestamp tampering permitted';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
 ELSIF current_user = 'p003e_mfa' THEN
  BEGIN
   EXECUTE 'SELECT password FROM public.users LIMIT 1';
   RAISE EXCEPTION 'MFA SQL reads password hash';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
   EXECUTE 'UPDATE public.company_role_assignments SET role_key=role_key WHERE false';
   RAISE EXCEPTION 'MFA can change company role';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
 ELSIF current_user = 'p003e_queue' THEN
  BEGIN
   EXECUTE 'SELECT mfa_secret FROM public.users LIMIT 1';
   RAISE EXCEPTION 'queue reads MFA secret';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
 ELSIF current_user = 'p003e_session' THEN
  BEGIN
   EXECUTE 'SELECT password FROM public.users LIMIT 1';
   RAISE EXCEPTION 'session broker reads password hash';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
 END IF;
END $proof$;
BEGIN;
DO $positive$
DECLARE probe_count integer;
BEGIN
 IF current_user = 'p003e_web' THEN
   EXECUTE 'SELECT count(*) FROM public.users WHERE email = ''p003c-eg@example.invalid''' INTO probe_count;
   IF probe_count <> 1 THEN RAISE EXCEPTION 'Web synthetic identity missing'; END IF;
 ELSIF current_user = 'p003e_mfa' THEN
   UPDATE public.users SET mfa_last_step=COALESCE(mfa_last_step,0)+1
     WHERE id=(SELECT id FROM public.users LIMIT 1);
   IF NOT FOUND THEN RAISE EXCEPTION 'MFA synthetic user missing'; END IF;
 ELSIF current_user = 'p003e_queue' THEN
   INSERT INTO public.jobs(queue,payload,attempts,available_at,created_at)
     VALUES ('p003e','{"synthetic":true}',0,0,0);
   IF NOT FOUND THEN RAISE EXCEPTION 'queue insert failed'; END IF;
 ELSIF current_user = 'p003e_session' THEN
   INSERT INTO public.sessions(id,payload,last_activity)
     VALUES ('p003e-role-smoke','synthetic-only',0);
   IF NOT FOUND THEN RAISE EXCEPTION 'session insert failed'; END IF;
 END IF;
END $positive$;
ROLLBACK;
SELECT 1 / CASE WHEN to_regclass('public.p003e_forbidden') IS NULL
 THEN 1 ELSE 0 END AS no_forbidden_ddl;
\echo 'PASS: role allowlist and denied operations; no Laravel workflow verified by this script'