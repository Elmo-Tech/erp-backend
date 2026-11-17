#!/usr/bin/env python3
"""P0-03H disposable UID/Unix-socket boundary over the existing P0-03G PHP PoC.

This is a synthetic CI experiment, NOT a Laravel session issuer or deployment service.
Only run against elmo_erp_test; socket accepts begin/verify, never seed/revoke/admin.
"""
import concurrent.futures
import grp
import hashlib
import hmac
import json
import os
from pathlib import Path
import pwd
import secrets
import signal
import socket
import stat
import struct
import subprocess
import sys
import threading
import time

BROKER = "p003h_broker"
WEB = "p003h_web"
OUTSIDER = "p003h_outsider"
ROOT = Path("/tmp/p003h-boundary")
PRIVATE = ROOT / "broker-private"
SOCKET_DIR = ROOT / "ipc"
SOCKET = str(SOCKET_DIR / "mfa.sock")
ENV_FILE = PRIVATE / "broker.json"
CLI = "/tmp/p003h-fixtures/p003g_broker_poc.php"
PATH = "/usr/local/bin:/usr/bin:/bin"


def check_disposable():
    if os.environ.get("APP_ENV") != "testing" or os.environ.get("DB_DATABASE") != "elmo_erp_test":
        raise RuntimeError("REFUSED: disposable P0-03H requires APP_ENV=testing and elmo_erp_test")


def php(request, configuration):
    p = subprocess.run(["php", CLI], input=json.dumps(request), text=True,
                       capture_output=True, env=configuration, timeout=9)
    if p.returncode:
        raise RuntimeError("disposable broker command failed (redacted)")
    return json.loads(p.stdout)


def broker_env():
    with open(ENV_FILE, encoding="utf-8") as f:
        values = json.load(f)
    return {"PATH": PATH, "APP_ENV": "testing", "DB_DATABASE": "elmo_erp_test",
            "P003G_BROKER_HOST": "127.0.0.1", "P003G_BROKER_PORT": "16433",
            "P003G_BROKER_USERNAME": "p003g_broker", "P003G_TEST_ADMIN": "1", **values}


def seed_or_revoke():
    operation, email = sys.argv[2:4]
    if operation not in ("seed", "revoke"):
        raise RuntimeError("invalid fixture operation")
    req = {"op": operation, "email": email}
    if operation == "seed":
        req.update({"id": int(sys.argv[4]), "password": sys.argv[5]})
    print(json.dumps(php(req, broker_env())))


def handle_client(connection, web_uid):
    with connection:
        connection.settimeout(10)
        try:
            # Linux SO_PEERCRED is kernel-reported; group/socket permissions alone are insufficient.
            _, uid, _ = struct.unpack("3i", connection.getsockopt(
                socket.SOL_SOCKET, socket.SO_PEERCRED, struct.calcsize("3i")))
            if uid != web_uid:
                result = {"ok": False, "reason": "peer_denied"}
            else:
                request_bytes = bytearray()
                while len(request_bytes) <= 4096 and not request_bytes.endswith(b"\n"):
                    part = connection.recv(1024)
                    if not part:
                        break
                    request_bytes.extend(part)
                if len(request_bytes) > 4096 or not request_bytes.endswith(b"\n"):
                    result = {"ok": False, "reason": "bad_request"}
                else:
                    request = json.loads(request_bytes)
                    if not isinstance(request, dict) or request.get("op") not in ("begin", "verify"):
                        result = {"ok": False, "reason": "unsupported_command"}
                    else:
                        # Ignore any user_id/mfa_verified supplied by the relay.
                        clean = {k: request[k] for k in ("op", "email", "password", "challenge", "code") if k in request}
                        answer = php(clean, broker_env())
                        if clean["op"] == "verify" and answer.get("ok") is True:
                            # No user_id / mfa_verified assertion is exported; no trusted session exists yet.
                            result = {"ok": True, "proof_only": True}
                        else:
                            result = {k: v for k, v in answer.items()
                                      if k in ("ok", "reason", "challenge", "expires_in")}
            connection.sendall((json.dumps(result) + "\n").encode())
        except (ValueError, KeyError, json.JSONDecodeError, socket.timeout, RuntimeError,
                subprocess.TimeoutExpired):
            try:
                connection.sendall(b'{"ok":false,"reason":"broker_unavailable"}\n')
            except OSError:
                pass


def serve():
    broker_uid = pwd.getpwnam(BROKER).pw_uid
    if os.geteuid() != broker_uid:
        raise RuntimeError("broker must execute under its dedicated UID")
    web_uid = pwd.getpwnam(WEB).pw_uid
    web_gid = pwd.getpwnam(WEB).pw_gid
    os.umask(0o007)
    if os.path.lexists(SOCKET):
        os.unlink(SOCKET)
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as listener:
        listener.bind(SOCKET)
        os.chown(SOCKET, -1, web_gid)
        os.chmod(SOCKET, 0o660)
        listener.listen(16)
        while True:
            conn, _ = listener.accept()
            threading.Thread(target=handle_client, args=(conn, web_uid), daemon=True).start()


def client():
    expected_uid = pwd.getpwnam(BROKER).pw_uid
    try:
        info = os.stat(SOCKET)
        if not stat.S_ISSOCK(info.st_mode) or info.st_uid != expected_uid:
            raise RuntimeError("unexpected broker socket owner")
        with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as conn:
            conn.settimeout(10)
            conn.connect(SOCKET)
            _, uid, _ = struct.unpack("3i", conn.getsockopt(
                socket.SOL_SOCKET, socket.SO_PEERCRED, struct.calcsize("3i")))
            if uid != expected_uid:
                raise RuntimeError("untrusted socket peer")
            req = sys.stdin.readline()
            if len(req) > 4096:
                raise RuntimeError("oversized request")
            conn.sendall((req.rstrip("\n") + "\n").encode())
            data = b""
            while not data.endswith(b"\n") and len(data) <= 8192:
                chunk = conn.recv(8192)
                if not chunk:
                    break
                data += chunk
            print(json.dumps(json.loads(data)))
    except (OSError, RuntimeError, ValueError, json.JSONDecodeError):
        print(json.dumps({"ok": False, "reason": "broker_unavailable"}))


def probe_secret():
    try:
        with open(ENV_FILE, encoding="utf-8") as f:
            f.read(1)
        print('{"denied":false}')
    except (PermissionError, FileNotFoundError):
        print('{"denied":true}')


def as_uid(user, mode, *args, payload=None):
    cmd = ["runuser", "-u", user, "--", "env", "-i", "PATH=" + PATH,
           "python3", str(Path(__file__).resolve()), mode, *map(str, args)]
    p = subprocess.run(cmd, input=json.dumps(payload) + "\n" if payload is not None else "",
                       text=True, capture_output=True, timeout=20)
    if p.returncode:
        raise AssertionError("subprocess failed: " + p.stderr[:160])
    return json.loads(p.stdout)


def otp(factor):
    step = int(time.time() // 30)
    digest = hmac.new(bytes.fromhex(factor), struct.pack(">Q", step), hashlib.sha1).digest()
    i = digest[-1] & 15
    return f"{(struct.unpack('>I', digest[i:i+4])[0] & 0x7fffffff)%1000000:06d}"


def start_server():
    p = subprocess.Popen(["runuser", "-u", BROKER, "--", "env", "-i",
        "PATH=" + PATH, "python3", str(Path(__file__).resolve()), "serve"],
        stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
    for _ in range(100):
        if p.poll() is not None:
            raise AssertionError("broker failed to start")
        if os.path.exists(SOCKET):
            return p
        time.sleep(.05)
    raise AssertionError("broker socket unavailable")


def stop_server(process):
    process.terminate()
    try:
        process.wait(timeout=5)
    except subprocess.TimeoutExpired:
        process.kill()
        process.wait(timeout=5)
    if os.path.lexists(SOCKET):
        os.unlink(SOCKET)


def deny_web_direct_database():
    # Separate SCRAM-authenticated PgBouncer instance; legacy CI uses trust, which
    # must NEVER be misrepresented as credential isolation.
    raw = ["runuser", "-u", WEB, "--", "env", "-i", "PATH=" + PATH,
           "PGPASSWORD=incorrect", "PGCONNECT_TIMEOUT=3", "psql", "-X",
           "-v", "ON_ERROR_STOP=1", "-h", "127.0.0.1", "-p", "16433",
           "-U", "p003g_broker", "-d", "elmo_erp_test", "-Atc", "SELECT 1"]
    denied = subprocess.run(raw, text=True, capture_output=True, timeout=10)
    assert denied.returncode != 0, "web impersonated broker on SQL transport"
    legacy = raw.copy()
    legacy[legacy.index("16433")] = "16432"
    assert subprocess.run(legacy, capture_output=True, text=True, timeout=10).returncode != 0, ("legacy trust pool permits broker impersonation")
    raw[7] = "PGPASSWORD=ci-only-web"
    raw[raw.index("p003g_broker")] = "p003e_web"
    raw[-1] = "SELECT count(*) FROM public.p003g_accounts"
    denied = subprocess.run(raw, text=True, capture_output=True, timeout=10)
    assert denied.returncode != 0 and "permission denied" in denied.stderr.lower(), denied.stderr


def run():
    check_disposable()
    assert os.geteuid() == 0, "root required only for disposable useradd/setup"
    for name in (BROKER, WEB, OUTSIDER):
        if name not in [p.pw_name for p in pwd.getpwall()]:
            subprocess.run(["useradd", "--system", "--no-create-home",
                            "--user-group", name], check=True)
    for name in (BROKER, OUTSIDER):
        subprocess.run(["usermod", "-a", "-G", WEB, name], check=True)
    b_uid, w_gid = pwd.getpwnam(BROKER).pw_uid, pwd.getpwnam(WEB).pw_gid
    ROOT.mkdir(mode=0o755, exist_ok=True)
    PRIVATE.mkdir(mode=0o700, exist_ok=True)
    SOCKET_DIR.mkdir(mode=0o770, exist_ok=True)
    os.chown(PRIVATE, b_uid, pwd.getpwnam(BROKER).pw_gid)
    os.chmod(PRIVATE, 0o700)
    os.chown(SOCKET_DIR, b_uid, w_gid)
    os.chmod(SOCKET_DIR, 0o2770)
    # New synthetic factor key, no Laravel APP_KEY, no customer secrets.
    ENV_FILE.write_text(json.dumps({"P003G_BROKER_PASSWORD": "ci-only-broker",
                                   "P003G_FACTOR_KEY": __import__("base64").b64encode(
                                       secrets.token_bytes(32)).decode()}), encoding="utf-8")
    os.chown(ENV_FILE, b_uid, pwd.getpwnam(BROKER).pw_gid)
    os.chmod(ENV_FILE, 0o600)
    assert as_uid(WEB, "probe") == {"denied": True}
    deny_web_direct_database()

    factors = {}
    for label, number in (("alice", 10301), ("bob", 10302), ("carol", 10303), ("david", 10304)):
        email = "p003h-" + label + "@example.invalid"
        result = as_uid(BROKER, "admin", "seed", email, number, "synthetic-" + label)
        assert result["ok"] is True
        factors[label] = (email, result["test_factor"])
    server = start_server()
    try:
        assert as_uid(OUTSIDER, "client", payload={"op": "begin"}) == {"ok": False, "reason": "peer_denied"}
        alice, a_factor = factors["alice"]
        bob, b_factor = factors["bob"]
        assert not as_uid(WEB, "client", payload={"op": "begin", "email": alice,
                                                  "password": "invalid"})["ok"]
        token = as_uid(WEB, "client", payload={"op": "begin", "email": alice,
                                               "password": "synthetic-alice"})["challenge"]
        assert not as_uid(WEB, "client", payload={"op": "verify", "email": bob,
                      "challenge": token, "code": otp(b_factor), "user_id": 10302,
                      "mfa_verified": True})["ok"]
        accepted = as_uid(WEB, "client", payload={"op": "verify", "email": alice,
                         "challenge": token, "code": otp(a_factor)})
        assert accepted == {"ok": True, "proof_only": True}, accepted
        assert not as_uid(WEB, "client", payload={"op": "verify", "email": alice,
                                                 "challenge": token, "code": otp(a_factor)})["ok"]

        pending = as_uid(WEB, "client", payload={"op": "begin", "email": bob,
                                                 "password": "synthetic-bob"})["challenge"]
        assert as_uid(BROKER, "admin", "revoke", bob)["ok"]
        assert not as_uid(WEB, "client", payload={"op": "verify", "email": bob,
                                                 "challenge": pending, "code": otp(b_factor)})["ok"]
        carol, c_factor = factors["carol"]
        pending = as_uid(WEB, "client", payload={"op": "begin", "email": carol,
                                                 "password": "synthetic-carol"})["challenge"]
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            responses = list(pool.map(lambda _: as_uid(WEB, "client", payload={
                "op": "verify", "email": carol, "challenge": pending,
                "code": otp(c_factor)}), range(2)))
        assert sorted(x["ok"] for x in responses) == [False, True], responses

        david, d_factor = factors["david"]
        pending = as_uid(WEB, "client", payload={"op": "begin", "email": david,
                                                 "password": "synthetic-david"})["challenge"]
        stop_server(server)
        assert not as_uid(WEB, "client", payload={"op": "verify", "email": david,
                                                 "challenge": pending, "code": otp(d_factor)})["ok"]
        server = start_server()
        assert as_uid(WEB, "client", payload={"op": "verify", "email": david,
                       "challenge": pending, "code": otp(d_factor)}) == {"ok": True, "proof_only": True}
        assert not as_uid(WEB, "client", payload={"op": "verify", "email": david,
                                                 "challenge": pending, "code": otp(d_factor)})["ok"]
        assert not as_uid(WEB, "client", payload={"op": "seed", "email": "bad"})["ok"]
        print("PASS: P0-03H distinct UIDs, unreadable credential/key file, SCRAM DB denial, "
              "socket server/client peer UID, admin command deny, broker-owned identity, "
              "forged/cross-user/replay/revocation/concurrency/restart/unavailable fail-closed")
        print("NOT VERIFIED: independently enforced Laravel sessions, Web-process-takeover resistance, "
              "production UID/container mounts, HA, rate policy, qualified human sign-off")
    finally:
        stop_server(server)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        raise SystemExit(2)
    if sys.argv[1] == "run":
        run()
    elif sys.argv[1] == "serve":
        serve()
    elif sys.argv[1] == "admin":
        seed_or_revoke()
    elif sys.argv[1] == "client":
        client()
    elif sys.argv[1] == "probe":
        probe_secret()
    else:
        raise SystemExit(2)
