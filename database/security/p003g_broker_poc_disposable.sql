-- P0-03G CI-ONLY broker-owned challenge tables. Synthetic identities; NOT a migration.
\set ON_ERROR_STOP on
SELECT CASE WHEN current_database()='elmo_erp_test' AND current_user='elmo'
  THEN 'true' ELSE 'false' END AS safe \gset
\if :safe
\else
 \echo 'REFUSED: disposable elmo_erp_test owner required'
 \quit 3
\endif
SELECT format('CREATE ROLE p003g_broker LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOINHERIT NOBYPASSRLS', :'broker_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='p003g_broker') \gexec
GRANT CONNECT ON DATABASE elmo_erp_test TO p003g_broker;
GRANT USAGE ON SCHEMA public TO p003g_broker;
CREATE TABLE public.p003g_accounts (
    email text PRIMARY KEY,
    user_id bigint NOT NULL UNIQUE,
    password_hash text NOT NULL,
    otp_cipher text NOT NULL,
    active boolean NOT NULL DEFAULT true,
    revocation_epoch bigint NOT NULL DEFAULT 0
);
CREATE TABLE public.p003g_challenges (
    token_hash char(64) PRIMARY KEY,
    email text NOT NULL REFERENCES public.p003g_accounts(email),
    created_epoch bigint NOT NULL,
    expires_epoch bigint NOT NULL,
    revocation_epoch bigint NOT NULL,
    attempts integer NOT NULL DEFAULT 0,
    consumed_epoch bigint NULL
);
CREATE INDEX p003g_challenges_account_time ON public.p003g_challenges(email, created_epoch);
CREATE TABLE public.p003g_audit (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    event text NOT NULL,
    account_digest char(64) NOT NULL,
    created_epoch bigint NOT NULL
);
REVOKE ALL ON public.p003g_accounts, public.p003g_challenges, public.p003g_audit FROM PUBLIC;
REVOKE ALL ON SEQUENCE public.p003g_audit_id_seq FROM PUBLIC;
GRANT SELECT, INSERT, UPDATE ON public.p003g_accounts, public.p003g_challenges TO p003g_broker;
GRANT INSERT ON public.p003g_audit TO p003g_broker;
GRANT USAGE ON SEQUENCE public.p003g_audit_id_seq TO p003g_broker;
-- Existing CI Web/MFA/Queue/Session SQL roles must not own broker state.
SELECT 1 / CASE WHEN
 NOT has_table_privilege('p003e_web','public.p003g_accounts','SELECT')
 AND NOT has_table_privilege('p003e_web','public.p003g_challenges','INSERT')
 AND NOT has_table_privilege('p003e_mfa','public.p003g_accounts','SELECT')
 AND NOT has_table_privilege('p003e_session','public.p003g_challenges','SELECT')
 AND NOT has_table_privilege('p003e_queue','public.p003g_audit','SELECT')
 AND has_table_privilege('p003g_broker','public.p003g_challenges','UPDATE')
 THEN 1 ELSE 0 END AS broker_principal_guard;
\echo 'PASS: disposable broker-only state and denied Web/MFA/Session/Queue reads'
