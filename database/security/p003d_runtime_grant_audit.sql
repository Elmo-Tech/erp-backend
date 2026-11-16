-- P0-03D fail-closed GRANT gap tests on synthetic CI only.
-- These tests explicitly prove current role CANNOT run MFA flows. This is a blocker.
\set ON_ERROR_STOP on
SELECT 1 / CASE WHEN current_database()='elmo_erp_test' AND current_user='p003c_runtime'
 THEN 1 ELSE 0 END AS safe;
SELECT 1 / CASE WHEN
 has_table_privilege(current_user,'users','SELECT')
 AND has_column_privilege(current_user,'users','updated_at','UPDATE')
 AND has_column_privilege(current_user,'companies','updated_at','UPDATE')
 AND has_column_privilege(current_user,'company_memberships','updated_at','UPDATE')
 AND has_column_privilege(current_user,'company_role_assignments','updated_at','UPDATE')
 AND NOT has_column_privilege(current_user,'users','mfa_secret','UPDATE')
 AND NOT has_column_privilege(current_user,'users','mfa_last_step','UPDATE')
 AND NOT has_table_privilege(current_user,'user_mfa_recovery_codes','SELECT')
 AND NOT has_table_privilege(current_user,'user_mfa_recovery_codes','DELETE')
 AND NOT has_table_privilege(current_user,'user_mfa_enrollment_authorizations','UPDATE')
 AND NOT has_table_privilege(current_user,'password_reset_tokens','SELECT')
 AND NOT has_table_privilege(current_user,'job_batches','SELECT')
 AND has_table_privilege(current_user,'sessions','DELETE')
 AND has_table_privilege(current_user,'jobs','UPDATE')
 AND has_table_privilege(current_user,'failed_jobs','INSERT')
 AND NOT has_table_privilege(current_user,'fiscal_years','SELECT')
 THEN 1 ELSE 0 END AS documented_grant_matrix_matches;
DO $$
BEGIN
 BEGIN
  EXECUTE 'UPDATE public.users SET mfa_last_step=mfa_last_step WHERE false';
  RAISE EXCEPTION 'MFA write unexpectedly allowed';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
  EXECUTE 'DELETE FROM public.user_mfa_recovery_codes WHERE false';
  RAISE EXCEPTION 'MFA recovery write unexpectedly allowed';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
  EXECUTE 'UPDATE public.user_mfa_enrollment_authorizations SET consumed_at=consumed_at WHERE false';
  RAISE EXCEPTION 'MFA grant consumption unexpectedly allowed';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
  EXECUTE 'UPDATE public.company_memberships SET company_id=company_id WHERE false';
  RAISE EXCEPTION 'foreign company membership reassignment allowed';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 BEGIN
  EXECUTE 'UPDATE public.company_role_assignments SET role_key=role_key WHERE false';
  RAISE EXCEPTION 'role grant re-assignment allowed';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
END $$;
\echo 'PASS: CI grant matrix matches; MFA/runtime compatibility remains FAILED/NOT APPROVED'
