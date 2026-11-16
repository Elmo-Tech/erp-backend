-- P0-03D: rollback-only signed tenant-claim experiment. Not a deployment recipe.
-- Ownership and verifier run as elmo ONLY on disposable elmo_erp_test, including via PgBouncer.
-- Threat tested: arbitrary SQL with p003c_runtime privileges, without access to an issuer.
\set ON_ERROR_STOP on
SELECT CASE WHEN current_database() = 'elmo_erp_test' AND current_user = 'elmo'
 THEN 'true' ELSE 'false' END AS safe \gset
\if :safe
\else
  \echo 'REFUSED: requires disposable elmo_erp_test with elmo migration owner'
  \quit 3
\endif
BEGIN;
-- Earlier P0-03C revocation-race step revokes the EG user. Restore its
-- synthetic membership ONLY inside this rollback-only probe transaction.
UPDATE company_memberships SET status='active', updated_at=now()
 WHERE user_id=(SELECT id FROM users WHERE email='p003c-eg@example.invalid')
   AND company_id='00000000-0000-4000-8000-0000000000c1';
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA p003d_private;
REVOKE ALL ON SCHEMA p003d_private FROM PUBLIC;
CREATE TABLE p003d_private.keys (id int PRIMARY KEY, secret bytea NOT NULL);
INSERT INTO p003d_private.keys VALUES (1, gen_random_bytes(32));
REVOKE ALL ON p003d_private.keys FROM PUBLIC;

-- This verifier has NO SQL-callable signing/issuing function.
-- Any future issuer would require independent authenticated authorization and
-- a credential/secret inaccessible to the SQL runtime role; NOT implemented.
CREATE FUNCTION p003d_private.verified_company()
RETURNS uuid LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path = pg_catalog, pg_temp AS $fn$
DECLARE
  claim text := current_setting('p003d.claim', true);
  fields text[];
  message text;
  expected text;
  target uuid;
BEGIN
  IF claim IS NULL OR length(claim) > 256 THEN RETURN NULL; END IF;
  fields := string_to_array(claim, '|');
  IF array_length(fields, 1) IS DISTINCT FROM 4
    OR fields[1] !~ '^[0-9a-f-]{36}$'
    OR fields[2] !~ '^[0-9]+$'
    OR fields[3] !~ '^[0-9]+$'
    OR fields[4] !~ '^[0-9a-f]{64}$'
    OR fields[3] <> txid_current()::text
  THEN RETURN NULL; END IF;
  message := fields[1] || '|' || fields[2] || '|' || fields[3];
  SELECT encode(public.hmac(convert_to(message, 'UTF8'), k.secret, 'sha256'), 'hex')
    INTO expected FROM p003d_private.keys AS k WHERE k.id = 1;
  IF expected IS NULL OR expected <> fields[4] THEN RETURN NULL; END IF;
  target := fields[1]::uuid;
  -- The DB also checks live identity and active membership; permissions are
  -- still evaluated by Laravel before any trusted issuer signs a claim.
  IF NOT EXISTS (
    SELECT 1 FROM public.users AS u
    JOIN public.company_memberships AS m ON m.user_id = u.id
    JOIN public.companies AS c ON c.id = m.company_id
    WHERE u.id::text = fields[2] AND u.is_active
      AND m.company_id = target AND m.status = 'active' AND c.status = 'active'
  ) THEN RETURN NULL; END IF;
  RETURN target;
EXCEPTION WHEN invalid_text_representation OR numeric_value_out_of_range THEN
  RETURN NULL;
END $fn$;
REVOKE ALL ON FUNCTION p003d_private.verified_company() FROM PUBLIC;
GRANT USAGE ON SCHEMA p003d_private TO p003c_runtime;
GRANT EXECUTE ON FUNCTION p003d_private.verified_company() TO p003c_runtime;

CREATE TABLE public.p003d_rows (
 id integer PRIMARY KEY, company_id uuid NOT NULL, body text NOT NULL
);
INSERT INTO public.p003d_rows VALUES
 (1, '00000000-0000-4000-8000-0000000000c1', 'synthetic EG'),
 (2, '00000000-0000-4000-8000-0000000000c2', 'synthetic IT');
GRANT SELECT, INSERT, UPDATE(body) ON public.p003d_rows TO p003c_runtime;
ALTER TABLE public.p003d_rows ENABLE ROW LEVEL SECURITY;
CREATE POLICY p003d_signed_probe ON public.p003d_rows FOR ALL TO p003c_runtime
 USING (company_id = p003d_private.verified_company())
 WITH CHECK (company_id = p003d_private.verified_company());

-- Simulate a SEPARATE privileged issuer (owner in disposable CI only).
-- Real issuer and authentication protocol DO NOT EXIST and must not be assumed.
SELECT concat_ws('|', '00000000-0000-4000-8000-0000000000c1',
   (SELECT id::text FROM users WHERE email='p003c-eg@example.invalid'),
   txid_current()::text,
   encode(public.hmac(convert_to(
      '00000000-0000-4000-8000-0000000000c1' || '|' ||
      (SELECT id::text FROM users WHERE email='p003c-eg@example.invalid') || '|' ||
      txid_current()::text, 'UTF8'), (SELECT secret FROM p003d_private.keys WHERE id=1), 'sha256'), 'hex')
 ) AS eg_claim \gset
SELECT concat_ws('|', '00000000-0000-4000-8000-0000000000c2',
   (SELECT id::text FROM users WHERE email='p003c-it@example.invalid'),
   txid_current()::text,
   encode(public.hmac(convert_to(
      '00000000-0000-4000-8000-0000000000c2' || '|' ||
      (SELECT id::text FROM users WHERE email='p003c-it@example.invalid') || '|' ||
      txid_current()::text, 'UTF8'), (SELECT secret FROM p003d_private.keys WHERE id=1), 'sha256'), 'hex')
 ) AS it_claim \gset

SAVEPOINT eg_probe;
SET LOCAL ROLE p003c_runtime;
SELECT 1 / CASE WHEN (SELECT count(*) FROM public.p003d_rows) = 0
 THEN 1 ELSE 0 END AS missing_claim_denied;
SELECT 1 / CASE WHEN NOT has_table_privilege(current_user,'p003d_private.keys','SELECT')
 AND NOT has_schema_privilege(current_user,'p003d_private','CREATE')
 AND (SELECT p.proowner <> (SELECT oid FROM pg_roles WHERE rolname=current_user) FROM pg_proc p WHERE p.oid = 'p003d_private.verified_company()'::regprocedure)
 THEN 1 ELSE 0 END AS private_signing_boundary;
DO $block$
BEGIN
  BEGIN
    EXECUTE 'SELECT secret FROM p003d_private.keys';
    RAISE EXCEPTION 'runtime unexpectedly read the MAC key';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
  BEGIN
    EXECUTE 'CREATE OR REPLACE FUNCTION p003d_private.verified_company() RETURNS uuid LANGUAGE SQL AS ''SELECT NULL::uuid''';
    RAISE EXCEPTION 'runtime unexpectedly replaced verifier';
  EXCEPTION WHEN insufficient_privilege THEN NULL;
  END;
END $block$;
SELECT set_config('p003d.claim', :'eg_claim', true);
SELECT 1 / CASE WHEN
 (SELECT count(*) FROM public.p003d_rows WHERE id=1)=1 AND
 (SELECT count(*) FROM public.p003d_rows WHERE id=2)=0
 THEN 1 ELSE 0 END AS eg_signed_scope;
-- Custom company GUC is forgeable, but the RLS policy must ignore it.
SELECT set_config('app.company_id','00000000-0000-4000-8000-0000000000c2',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM public.p003d_rows WHERE id=1)=1
 AND (SELECT count(*) FROM public.p003d_rows WHERE id=2)=0
 THEN 1 ELSE 0 END AS guc_switch_has_no_effect;
UPDATE public.p003d_rows SET body='should-not-write' WHERE id=2;
SELECT 1 / CASE WHEN (SELECT count(*) FROM public.p003d_rows WHERE id=2)=0
 THEN 1 ELSE 0 END AS it_update_invisible;
DO $block$
BEGIN
  BEGIN
    INSERT INTO public.p003d_rows VALUES
      (3,'00000000-0000-4000-8000-0000000000c2','forbidden');
    RAISE EXCEPTION 'cross-company INSERT unexpectedly allowed';
  EXCEPTION WHEN insufficient_privilege OR check_violation THEN NULL;
  END;
END $block$;
-- Changed signed subject must fail: no issuer/key is available to SQL runtime.
SELECT set_config('p003d.claim', replace(:'eg_claim',
  '00000000-0000-4000-8000-0000000000c1',
  '00000000-0000-4000-8000-0000000000c2'), true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM public.p003d_rows)=0
 THEN 1 ELSE 0 END AS altered_company_signature_denied;
SELECT set_config('p003d.claim','unsigned|fake|1|bad',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM public.p003d_rows)=0
 THEN 1 ELSE 0 END AS unsigned_claim_denied;
ROLLBACK TO SAVEPOINT eg_probe;
SAVEPOINT it_probe;
SET LOCAL ROLE p003c_runtime;
SELECT set_config('p003d.claim', :'it_claim', true);
SELECT set_config('app.company_id','00000000-0000-4000-8000-0000000000c1',true);
SELECT 1 / CASE WHEN (SELECT count(*) FROM public.p003d_rows WHERE id=2)=1
 AND (SELECT count(*) FROM public.p003d_rows WHERE id=1)=0
 THEN 1 ELSE 0 END AS it_signed_scope_in_spite_of_forged_guc;
-- A valid signed EG claim, if stolen, remains a bearer capability for EG in
-- this transaction. This probe does NOT establish issuer-side authorization.
ROLLBACK TO SAVEPOINT it_probe;
ROLLBACK;
SELECT 1 / CASE WHEN to_regclass('public.p003d_rows') IS NULL
 AND NOT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'p003d_private')
 THEN 1 ELSE 0 END AS no_permanent_signed_policy_or_secret;
\echo 'PASS: synthetic MAC claim prevents tested EG/IT GUC/signature spoofing; issuer, replay, and production safety NOT VERIFIED'
