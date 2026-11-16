#!/usr/bin/env python3
"""P0-03C: parallel real Laravel HTTP sessions against disposable PgBouncer runtime."""
import concurrent.futures
import http.cookiejar
import json
import os
import urllib.error
import urllib.parse
import urllib.request

if not (os.environ.get("APP_ENV") == "testing"
        and os.environ.get("DB_DATABASE") == "elmo_erp_test"
        and os.environ.get("DB_USERNAME") == "p003c_runtime"
        and os.environ.get("DB_PORT") == "16432"):
    raise SystemExit("REFUSED: isolated P0-03C runtime environment required")

BASE = "http://127.0.0.1:18031"
EG = "00000000-0000-4000-8000-0000000000c1"
IT = "00000000-0000-4000-8000-0000000000c2"
ORIGIN = "http://localhost:5173"


def client(company, slug):
    cookies = http.cookiejar.CookieJar()
    handler = urllib.request.HTTPCookieProcessor(cookies)
    opener = urllib.request.build_opener(handler)

    def request(path, payload=None):
        headers = {"Accept": "application/json", "Origin": ORIGIN,
                   "Referer": ORIGIN + "/"}
        data = None
        if payload is not None:
            headers["Content-Type"] = "application/json"
            data = json.dumps(payload).encode()
            xsrf = next((c.value for c in cookies if c.name == "XSRF-TOKEN"), None)
            if xsrf is not None:
                headers["X-XSRF-TOKEN"] = urllib.parse.unquote(xsrf)
        try:
            with opener.open(urllib.request.Request(BASE + path, data=data, headers=headers), timeout=15) as resp:
                return resp.status, json.loads(resp.read())
        except urllib.error.HTTPError as exc:
            return exc.code, json.loads(exc.read())

    status, _ = request("/sanctum/csrf-cookie")
    if status != 204:
        raise RuntimeError("Sanctum CSRF cookie setup failed: " + str(status))
    status, body = request("/api/v1/auth/login",
                           {"email": "p003c-" + slug + "@example.invalid",
                            "password": "p003c-test-only"})
    if status != 200:
        raise RuntimeError("HTTP login denied: " + str(status) + " " + str(body))
    status, body = request("/api/v1/companies/switch", {"company_id": company})
    if status != 200 or body.get("company_id") != company:
        raise RuntimeError("Server failed authorized company selection: " + str((status, body)))
    return request


def main():
    eg_request = client(EG, "eg")
    it_request = client(IT, "it")
    jobs = [(eg_request, EG, IT), (it_request, IT, EG)] * 8

    def exercise(item):
        request, own, foreign = item
        status, body = request("/api/v1/companies/authorized-read")
        if status != 200 or body.get("company", {}).get("id") != own:
            raise RuntimeError("Concurrent HTTP company data leaked/wrong: " + str((status, body)))
        # Direct attempts to select the foreign tenant must not authorize reads.
        status, _ = request("/api/v1/companies/switch", {"company_id": foreign})
        if status != 403:
            raise RuntimeError("Cross-legal-company switch accepted: " + str(status))
        return True

    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        results = list(pool.map(exercise, jobs))
    if len(results) != 16 or not all(results):
        raise RuntimeError("Missing simultaneous HTTP results")
    print("PASS: 16 real concurrent authenticated HTTP requests, EG and IT isolated; denied foreign company switches")


if __name__ == "__main__":
    main()
