\set ON_ERROR_STOP on
-- Owner-executed, synthetic PostgreSQL 18 only. Never use against real databases.
-- All credentials come from psql variables; never record literal passwords.
BEGIN;
DO $guard$
BEGIN
  IF current_database() NOT IN ('elmo_erp_test', 'elmo_erp_compose_ci')
     OR current_user <> 'elmo' THEN
    RAISE EXCEPTION 'P0-04-ACL refuses non-synthetic database or non-owner';
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname IN
    ('elmo_runtime', 'elmo_worker', 'elmo_mfa_executor')) THEN
    RAISE EXCEPTION 'P0-04-ACL refuses existing role modification';
  END IF;
END
$guard$;
SET LOCAL password_encryption = 'scram-sha-256';
SELECT format(
  'CREATE ROLE elmo_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS PASSWORD %L',
  :'runtime_password'
) \gexec
SELECT format(
  'CREATE ROLE elmo_worker LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS PASSWORD %L',
  :'worker_password'
) \gexec
CREATE ROLE elmo_mfa_executor NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION NOBYPASSRLS;

-- pg_database identifiers are fixed by the guard above, never user-supplied.
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', current_database()) \gexec
SELECT format('GRANT CONNECT ON DATABASE %I TO elmo_runtime, elmo_worker', current_database()) \gexec
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO elmo_runtime, elmo_worker, elmo_mfa_executor;
-- Web: authentication, company membership and static metadata are read-only.
GRANT SELECT ON TABLE public.users, public.global_role_assignments,
 public.company_memberships, public.company_role_assignments,
 public.currencies, public.companies,
 public.user_mfa_enrollment_authorizations TO elmo_runtime;
-- Explicit synthetic company CRUD support; no finance posting/API follows.
GRANT INSERT, UPDATE, DELETE ON TABLE public.companies TO elmo_runtime;
-- Database session/cache and Web queue enqueue fallback; Compose uses Redis.
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE
 public.sessions, public.cache, public.cache_locks TO elmo_runtime;
GRANT SELECT, INSERT ON TABLE public.jobs TO elmo_runtime;
GRANT USAGE, SELECT ON SEQUENCE public.jobs_id_seq TO elmo_runtime;
-- Worker has only the queue and synthetic draft writing powers, no user or MFA writes.
GRANT SELECT ON TABLE public.users, public.global_role_assignments,
 public.company_memberships, public.company_role_assignments,
 public.companies, public.currencies, public.fiscal_years,
 public.accounting_periods, public.ledger_accounts TO elmo_worker;
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.jobs,
 public.job_batches, public.failed_jobs,
 public.journal_entries, public.journal_lines TO elmo_worker;
GRANT USAGE, SELECT ON SEQUENCE public.jobs_id_seq,
 public.failed_jobs_id_seq TO elmo_worker;
-- NOLOGIN, non-superuser owner for narrowly parameterized MFA operations.
-- Web is never a member and receives NO raw MFA table write grants.
GRANT SELECT ON public.users, public.user_mfa_enrollment_authorizations,
 public.user_mfa_recovery_codes TO elmo_mfa_executor;
GRANT UPDATE (mfa_secret, mfa_last_step, updated_at) ON public.users TO elmo_mfa_executor;
GRANT UPDATE (consumed_at, updated_at) ON public.user_mfa_enrollment_authorizations TO elmo_mfa_executor;
GRANT INSERT, DELETE ON public.user_mfa_recovery_codes TO elmo_mfa_executor;

CREATE FUNCTION public.p004_consume_mfa_step(p_user bigint, p_step bigint)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, pg_temp AS $fn$
DECLARE changed integer;
BEGIN
  IF session_user <> 'elmo_runtime' OR p_step IS NULL OR
     p_step NOT BETWEEN floor(extract(epoch FROM clock_timestamp()) / 30)::bigint - 1
                    AND floor(extract(epoch FROM clock_timestamp()) / 30)::bigint + 1 THEN
    RETURN false;
  END IF;
  UPDATE public.users SET mfa_last_step=p_step, updated_at=now()
  WHERE id=p_user AND is_active=true AND mfa_secret IS NOT NULL
    AND (mfa_last_step IS NULL OR mfa_last_step < p_step);
  GET DIAGNOSTICS changed=ROW_COUNT;
  RETURN changed=1;
END $fn$;

CREATE FUNCTION public.p004_consume_recovery(p_user bigint, p_hash text)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, pg_temp AS $fn$
DECLARE changed integer;
BEGIN
  IF session_user <> 'elmo_runtime' OR p_hash !~ '^[a-f0-9]{64}$' THEN
    RETURN false;
  END IF;
  DELETE FROM public.user_mfa_recovery_codes
  WHERE user_id=p_user AND code_hash=p_hash;
  GET DIAGNOSTICS changed=ROW_COUNT;
  RETURN changed=1;
END $fn$;

CREATE FUNCTION public.p004_enroll_mfa(
  p_user bigint, p_encrypted_secret text, p_step bigint, p_digests jsonb)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, pg_temp AS $fn$
DECLARE changed integer; digest text;
BEGIN
  IF session_user <> 'elmo_runtime' OR p_encrypted_secret IS NULL OR
     length(p_encrypted_secret) < 80 OR
     p_step NOT BETWEEN floor(extract(epoch FROM clock_timestamp()) / 30)::bigint - 1
                    AND floor(extract(epoch FROM clock_timestamp()) / 30)::bigint + 1 OR
     jsonb_typeof(p_digests) IS DISTINCT FROM 'array' OR
     jsonb_array_length(p_digests) <> 8 THEN RETURN false;
  END IF;
  IF EXISTS(SELECT 1 FROM jsonb_array_elements_text(p_digests) AS e(value)
     WHERE e.value !~ '^[a-f0-9]{64}$') OR
     (SELECT count(DISTINCT e.value) FROM jsonb_array_elements_text(p_digests) AS e(value)) <> 8
     THEN RETURN false;
  END IF;
  PERFORM 1 FROM public.users WHERE id=p_user AND is_active=true
     AND mfa_secret IS NULL FOR UPDATE;
  IF NOT FOUND THEN RETURN false; END IF;
  UPDATE public.user_mfa_enrollment_authorizations
     SET consumed_at=now(), updated_at=now()
     WHERE user_id=p_user AND consumed_at IS NULL AND expires_at>now();
  GET DIAGNOSTICS changed=ROW_COUNT;
  IF changed<>1 THEN RETURN false; END IF;
  UPDATE public.users SET mfa_secret=p_encrypted_secret,
     mfa_last_step=p_step, updated_at=now() WHERE id=p_user;
  FOR digest IN SELECT e.value FROM jsonb_array_elements_text(p_digests) AS e(value)
  LOOP
    INSERT INTO public.user_mfa_recovery_codes(id,user_id,code_hash,created_at)
    VALUES (gen_random_uuid(),p_user,digest,now());
  END LOOP;
  RETURN true;
END $fn$;

ALTER FUNCTION public.p004_consume_mfa_step(bigint,bigint) OWNER TO elmo_mfa_executor;
ALTER FUNCTION public.p004_consume_recovery(bigint,text) OWNER TO elmo_mfa_executor;
ALTER FUNCTION public.p004_enroll_mfa(bigint,text,bigint,jsonb) OWNER TO elmo_mfa_executor;
REVOKE ALL ON FUNCTION public.p004_consume_mfa_step(bigint,bigint) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.p004_consume_recovery(bigint,text) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.p004_enroll_mfa(bigint,text,bigint,jsonb) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.p004_consume_mfa_step(bigint,bigint),
 public.p004_consume_recovery(bigint,text),
 public.p004_enroll_mfa(bigint,text,bigint,jsonb) TO elmo_runtime;
-- No default privileges and no role memberships. Future migration grants are manual.
COMMIT;
SELECT 'PASS: synthetic object-level ACL installed; MFA functions use NOLOGIN non-superuser owner' AS result;
