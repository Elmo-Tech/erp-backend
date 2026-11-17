#!/usr/bin/env python3
"""P0-03G: disposable CLI broker protocol checks; NOT Laravel integration or OS sandbox proof."""
import base64
import concurrent.futures
import hashlib
import hmac
import json
import os
import secrets
import struct
import subprocess
import time

assert os.getenv("APP_ENV") == "testing" and os.getenv("DB_DATABASE") == "elmo_erp_test"
assert os.getenv("P003G_BROKER_USERNAME") == "p003g_broker"
assert os.getenv("P003G_TEST_ADMIN") == "1"
assert os.getenv("P003G_BROKER_HOST") == "127.0.0.1"

ENV = os.environ.copy()

def cmd(operation, **fields):
    p = subprocess.run(
        ["php", "tests/integration/p003g_broker_poc.php"],
        input=json.dumps({"op": operation, **fields}), text=True, capture_output=True,
        env=ENV, timeout=15)
    if p.returncode != 0:
        raise AssertionError(f"broker command {operation} failed with exit {p.returncode}: {p.stderr[:120]}")
    return json.loads(p.stdout)

def owner(sql):
    p = subprocess.run(["psql","-X","-v","ON_ERROR_STOP=1","-h","127.0.0.1",
        "-p","5432","-U","elmo","-d","elmo_erp_test","-Atc",sql],
        env={**ENV,"PGPASSWORD":"test-only"},text=True,capture_output=True,timeout=15)
    if p.returncode:
        raise AssertionError(f"owner SQL failed: {p.stderr[:160]}")
    return p.stdout.strip()

def otp(factor, offset=0):
    step = int(time.time() // 30) + offset
    digest = hmac.new(bytes.fromhex(factor),struct.pack(">Q",step),hashlib.sha1).digest()
    i = digest[-1] & 15
    return f"{(struct.unpack('>I',digest[i:i+4])[0] & 0x7fffffff)%1000000:06d}"

def check(a, ok):
    assert a.get("ok") is ok, a

def sql_web_deny(port):
    p = subprocess.run(["psql","-X","-v","ON_ERROR_STOP=1","-h","127.0.0.1",
        "-p",str(port),"-U","p003e_web","-d","elmo_erp_test","-Atc",
        "SELECT count(*) FROM public.p003g_accounts"],
        env={**ENV,"PGPASSWORD":"ci-only-web"},capture_output=True,text=True,timeout=15)
    assert p.returncode != 0 and "permission denied" in p.stderr.lower(), p.stderr

def main():
    # An independent process handles every command; only this test driver has the broker env.
    # Running under the same CI OS account does NOT prove kernel-enforced separation.
    alice = cmd("seed", email="p003g-a@example.invalid", id=10001, password="synthetic-secret-a")
    bob = cmd("seed", email="p003g-b@example.invalid", id=10002, password="synthetic-secret-b")
    carol = cmd("seed", email="p003g-c@example.invalid", id=10003, password="synthetic-secret-c")
    for port in (5432, 16432):
        sql_web_deny(port)
    check(cmd("begin", email="p003g-a@example.invalid",password="incorrect"),False)
    challenge=cmd("begin",email="p003g-a@example.invalid",password="synthetic-secret-a")
    check(challenge,True)
    token=challenge["challenge"]
    check(cmd("verify",email="p003g-b@example.invalid",challenge=token,code=otp(bob["test_factor"])),False)
    verified=cmd("verify",email="p003g-a@example.invalid",challenge=token,code=otp(alice["test_factor"]))
    check(verified,True)
    assert verified["user_id"] == 10001
    check(cmd("verify",email="p003g-a@example.invalid",challenge=token,code=otp(alice["test_factor"])),False)
    another=cmd("begin",email="p003g-a@example.invalid",password="synthetic-secret-a")
    check(cmd("verify",email="p003g-a@example.invalid",challenge=another["challenge"],
              code=otp(alice["test_factor"])),False) # TOTP step already consumed

    pending=cmd("begin",email="p003g-b@example.invalid",password="synthetic-secret-b")
    check(cmd("revoke",email="p003g-b@example.invalid"),True)
    check(cmd("verify",email="p003g-b@example.invalid",challenge=pending["challenge"],code=otp(bob["test_factor"])),False)
    expired=cmd("begin",email="p003g-b@example.invalid",password="synthetic-secret-b")
    owner("UPDATE public.p003g_challenges SET expires_epoch=0 WHERE token_hash='"
          +hashlib.sha256(expired["challenge"].encode()).hexdigest()+"'")
    check(cmd("verify",email="p003g-b@example.invalid",challenge=expired["challenge"],code=otp(bob["test_factor"])),False)

    # Force a broker audit INSERT error. Successful factor consume MUST roll back.
    racing=cmd("begin",email="p003g-c@example.invalid",password="synthetic-secret-c")
    digest=hashlib.sha256(racing["challenge"].encode()).hexdigest()
    owner("ALTER TABLE public.p003g_audit ADD CONSTRAINT p003g_inject_failure CHECK (event <> 'factor_verified')")
    failed=subprocess.run(["php","tests/integration/p003g_broker_poc.php"],
        input=json.dumps({"op":"verify","email":"p003g-c@example.invalid",
                          "challenge":racing["challenge"],"code":otp(carol["test_factor"])}),
        text=True,capture_output=True,env=ENV,timeout=15)
    assert failed.returncode != 0 and "failed" in failed.stderr.lower()
    assert owner("SELECT consumed_epoch IS NULL FROM public.p003g_challenges WHERE token_hash='"+digest+"'") == "t"
    assert owner("SELECT last_totp_step FROM public.p003g_accounts WHERE email='p003g-c@example.invalid'") == "-1"
    owner("ALTER TABLE public.p003g_audit DROP CONSTRAINT p003g_inject_failure")

    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as executor:
        results=list(executor.map(lambda _: cmd("verify",email="p003g-c@example.invalid",
                challenge=racing["challenge"],code=otp(carol["test_factor"])),range(2)))
    assert sorted(result["ok"] for result in results)==[False,True],results
    events=[event["event"] for event in cmd("inspect",email="test-only")["audit"]]
    for event in ("password_denied","challenge_created","factor_denied","factor_verified","revoked","challenge_denied"):
        assert event in events, event
    assert not any("synthetic-secret" in x or "test_factor" in x for x in events)
    print("PASS: P0-03G disposable broker CLI, PG18+PgBouncer Web denial, challenge binding/replay/expiry/revoke, concurrent consume, rollback and redacted audit")
    print("NOT VERIFIED: kernel process isolation, HTTP/Sanctum integration, independent session issuance, real deployment ACL/auth, production availability")

if __name__ == "__main__":
    main()
