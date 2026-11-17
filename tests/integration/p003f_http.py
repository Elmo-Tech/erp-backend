#!/usr/bin/env python3
"""P0-03F: real HTTP split Web/MFA/Session principals on disposable PgBouncer."""
import base64
import hashlib
import hmac
import http.cookiejar
import json
import os
import struct
import time
import urllib.error
import urllib.parse
import urllib.request

if not (os.environ.get("APP_ENV") == "testing"
        and os.environ.get("DB_DATABASE") == "elmo_erp_test"
        and os.environ.get("DB_USERNAME") == "p003e_web"
        and os.environ.get("DB_PORT") == "16432"
        and os.environ.get("ERP_ROLE_PARTITION_REQUIRED") == "true"
        and os.environ.get("ERP_MFA_DB_CONNECTION") == "pgsql_mfa"
        and os.environ.get("SESSION_CONNECTION") == "pgsql_session"):
    raise SystemExit("REFUSED: real disposable partitioned HTTP required")

BASE = "http://127.0.0.1:18032"
ORIGIN = "http://localhost:5173"
EG = "00000000-0000-4000-8000-0000000000c1"
IT = "00000000-0000-4000-8000-0000000000c2"


class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        status, _ = self.call("/sanctum/csrf-cookie")
        assert status == 204, ("csrf", status)

    def call(self, path, payload=None):
        headers = {"Accept": "application/json", "Origin": ORIGIN, "Referer": ORIGIN + "/"}
        data = None
        if payload is not None:
            data = json.dumps(payload).encode()
            headers["Content-Type"] = "application/json"
            xsrf = next((c.value for c in self.cookies if c.name == "XSRF-TOKEN"), None)
            if xsrf:
                headers["X-XSRF-TOKEN"] = urllib.parse.unquote(xsrf)
        req = urllib.request.Request(BASE + path, data=data, headers=headers)
        try:
            with self.opener.open(req, timeout=15) as response:
                raw = response.read()
                return response.status, json.loads(raw) if raw else {}
        except urllib.error.HTTPError as exc:
            raw = exc.read()
            return exc.code, json.loads(raw) if raw else {}


def assert_status(value, expected):
    if value[0] != expected:
        raise AssertionError(f"Expected HTTP {expected}, got {value}")


def totp(secret):
    # Generated only for this synthetic disposable test factor.
    key = base64.b32decode(secret)
    step = int(time.time() // 30)
    digest = hmac.new(key, struct.pack(">Q", step), hashlib.sha1).digest()
    off = digest[-1] & 15
    value = (struct.unpack(">I", digest[off:off + 4])[0] & 0x7fffffff) % 1000000
    return f"{value:06d}", step


def main():
    mfa = Client()
    auth = "/api/v1/auth"
    creds = {"email": "p003f-mfa@example.invalid", "password": "p003f-test-only"}
    assert_status(mfa.call(auth + "/login", creds), 202)
    assert_status(mfa.call(auth + "/mfa/status"), 200)
    start = mfa.call(auth + "/mfa/enroll/start")
    assert_status(start, 200)
    secret = start[1]["secret"]
    first, enrolled_step = totp(secret)
    confirm = mfa.call(auth + "/mfa/enroll/confirm", {"code": first})
    assert_status(confirm, 200)
    codes = confirm[1]["recovery_codes"]
    assert len(codes) == 8
    assert_status(mfa.call(auth + "/me"), 200)
    assert_status(mfa.call(auth + "/logout", {}), 200)
    assert_status(mfa.call(auth + "/login", creds), 202)
    # Enrollment's TOTP step is already consumed and must not be replayed.
    assert_status(mfa.call(auth + "/mfa/verify", {"code": first}), 422)
    assert_status(mfa.call(auth + "/mfa/verify", {"code": codes[0]}), 200)
    assert_status(mfa.call(auth + "/logout", {}), 200)
    assert_status(mfa.call(auth + "/login", creds), 202)
    assert_status(mfa.call(auth + "/mfa/verify", {"code": codes[0]}), 422)
    assert_status(mfa.call(auth + "/mfa/verify", {"code": codes[1]}), 200)
    assert_status(mfa.call(auth + "/logout", {}), 200)
    assert_status(mfa.call(auth + "/login", creds), 202)
    # Wait for a new step, never re-use an already consumed TOTP counter.
    while int(time.time() // 30) <= enrolled_step:
        time.sleep(0.2)
    code, _ = totp(secret)
    assert_status(mfa.call(auth + "/mfa/verify", {"code": code}), 200)
    assert_status(mfa.call(auth + "/logout", {}), 200)

    for own, foreign, slug in [(EG, IT, "eg"), (IT, EG, "it")]:
        c = Client()
        assert_status(c.call(auth + "/login", {
            "email": f"p003c-{slug}@example.invalid",
            "password": "p003c-test-only",
        }), 200)
        assert_status(c.call("/api/v1/companies/switch", {"company_id": foreign}), 403)
        assert_status(c.call("/api/v1/companies/switch", {"company_id": own}), 200)
        read = c.call("/api/v1/companies/authorized-read")
        assert_status(read, 200)
        assert read[1]["company"]["id"] == own, read
        assert_status(c.call(auth + "/logout", {}), 200)
    print("PASS: real split-principal pooled HTTP, enrollment, one-time recovery, TOTP anti-replay, logout, EG/IT both directions")


if __name__ == "__main__":
    main()
