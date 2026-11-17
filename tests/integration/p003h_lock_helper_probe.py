#!/usr/bin/env python3
"""P0-03H adversarial lock-helper check. Expected vulnerability == test detection PASS,
NOT a security PASS. Uses disposable fixtures and live PG18/PgBouncer."""
import os
import subprocess
import time

assert os.getenv("APP_ENV") == "testing" and os.getenv("DB_DATABASE") == "elmo_erp_test"

def env(role):
    return {**os.environ, "PGPASSWORD": {"elmo":"test-only","p003e_web":"ci-only-web"}[role]}

def run(role, sql, port, timeout=15):
    return subprocess.run(["psql","-X","-v","ON_ERROR_STOP=1","-h","127.0.0.1",
                           "-p",str(port),"-U",role,"-d","elmo_erp_test","-Atc",sql],
                          capture_output=True,text=True,env=env(role),timeout=timeout)

meta = run("elmo", """SELECT p.prosecdef::text||'|'||pg_get_userbyid(p.proowner)||'|'||
                    coalesce(array_to_string(p.proconfig,','),'')||'|'||
                    (EXISTS (SELECT 1 FROM aclexplode(coalesce(p.proacl, acldefault('f', p.proowner))) acl WHERE acl.grantee=0 AND acl.privilege_type='EXECUTE'))::text||'|'||
                    has_function_privilege('p003e_web',p.oid,'EXECUTE')::text
                    FROM pg_proc p WHERE p.oid='public.p003f_lock_authorization_scope(bigint,uuid)'::regprocedure""",5432)
assert meta.returncode == 0,meta.stderr
fields=meta.stdout.strip().split("|")
assert fields[0]=="true" and fields[1]=="elmo" and "pg_catalog" in fields[2]
assert fields[3]=="false" and fields[4]=="true",fields

row=run("p003e_web","SELECT m.user_id||'|'||m.company_id||'|'||m.id FROM public.company_memberships m JOIN public.companies c ON c.id=m.company_id WHERE m.status='active' AND c.status='active' LIMIT 1",16432)
assert row.returncode == 0 and row.stdout.strip(),row.stderr
user_id,company_id,membership_id=row.stdout.strip().split("|")
sql=f"BEGIN; SELECT public.p003f_lock_authorization_scope({int(user_id)},'{company_id}'::uuid); SELECT pg_sleep(3); COMMIT;"
web=subprocess.Popen(["psql","-X","-v","ON_ERROR_STOP=1","-h","127.0.0.1","-p","16432",
                      "-U","p003e_web","-d","elmo_erp_test","-Atc",sql],
                     text=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=env("p003e_web"))
try:
    time.sleep(.8)
    owner=run("elmo",f"BEGIN; SET LOCAL lock_timeout='200ms'; UPDATE public.company_memberships SET status=status WHERE id='{membership_id}'::uuid; ROLLBACK;",5432,8)
    assert owner.returncode != 0 and "lock timeout" in owner.stderr.lower(), owner.stderr
    stdout,stderr=web.communicate(timeout=8)
    assert web.returncode == 0 and "t" in stdout,stderr
finally:
    if web.poll() is None:
        web.kill()
        web.wait()
print("PASS DETECTED SECURITY FAIL: Web-role caller chooses another user's lock target and blocks owner writer under PG18/PgBouncer")
print("PASS AUDITED: SECURITY DEFINER owner elmo; PUBLIC execute revoked; Web EXECUTE granted; search_path includes pg_catalog")
print("NOT VERIFIED: dedicated least-privilege function ownership, signed caller binding, production denial-of-service limits")
