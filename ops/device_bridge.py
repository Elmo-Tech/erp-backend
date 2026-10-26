"""Read-only Hikvision LAN bridge. No device users/settings/biometrics are written.

Run on an always-on office computer: python3 ops/device_bridge.py
Configuration is read from environment variables; see docs/LAN_DEVICE_BRIDGE.md.
"""

import datetime as dt
import hashlib
import json
import logging
import os
import sqlite3
import time
import urllib.parse
import urllib.request
import uuid


def post_json(opener, url, payload, headers=None):
    request = urllib.request.Request(
        url, json.dumps(payload).encode(),
        {"Content-Type": "application/json", "Accept": "application/json", **(headers or {})},
        method="POST",
    )
    with opener.open(request, timeout=30) as response:
        return json.load(response)


def open_spool(path):
    database = sqlite3.connect(path)
    database.execute("PRAGMA journal_mode=WAL")
    database.execute("CREATE TABLE IF NOT EXISTS events (id TEXT PRIMARY KEY, occurred TEXT, payload TEXT NOT NULL, sent INTEGER NOT NULL DEFAULT 0)")
    database.execute("CREATE TABLE IF NOT EXISTS state (key TEXT PRIMARY KEY, value TEXT NOT NULL)")
    database.commit()
    return database


def collect(database, opener, device_url, initial_since):
    saved = database.execute("SELECT value FROM state WHERE key='last_poll'").fetchone()
    since = dt.datetime.fromisoformat(saved[0] if saved else initial_since) - dt.timedelta(minutes=10)
    until = dt.datetime.now().astimezone()
    search_id = str(uuid.uuid4())
    position = 0
    while True:
        result = post_json(opener, device_url + "/ISAPI/AccessControl/AcsEvent?format=json", {
            "AcsEventCond": {"searchID": search_id, "searchResultPosition": position,
                             "maxResults": 30, "major": 0, "minor": 0, "picEnable": False,
                             "startTime": since.isoformat(timespec="seconds"),
                             "endTime": until.isoformat(timespec="seconds")},
        })
        envelope = result.get("AcsEvent", result)
        if not isinstance(envelope, (list, dict)) or (isinstance(envelope, dict) and "InfoList" not in envelope and "numOfMatches" not in envelope):
            raise ValueError("Terminal did not return an event-search result; checkpoint kept unchanged")
        rows = envelope if isinstance(envelope, list) else envelope.get("InfoList", [])
        if not isinstance(rows, list):
            raise ValueError("Unexpected terminal event response; checkpoint kept unchanged")
        for row in rows:
            # Non-person/device-maintenance events are not attendance punches.
            if not (row.get("employeeNoString") or row.get("employeeNo") or row.get("cardNo")):
                continue
            occurred = row.get("time") or row.get("dateTime")
            if not occurred:
                raise ValueError("Terminal returned a punch without a timestamp")
            payload = json.dumps(row, sort_keys=True, separators=(",", ":"))
            key = hashlib.sha256(payload.encode()).hexdigest()
            ordering_time = dt.datetime.fromisoformat(occurred).astimezone(dt.timezone.utc).isoformat()
            database.execute("INSERT OR IGNORE INTO events (id, occurred, payload) VALUES (?, ?, ?)", (key, ordering_time, payload))
        database.commit()  # Durable even if a later page or outbound request fails.
        position += len(rows)
        total = envelope.get("totalMatches") if isinstance(envelope, dict) else None
        if not rows or (total is not None and position >= int(total)) or (total is None and len(rows) < 30):
            break
        if position > 100000:
            raise ValueError("Event window too large; checkpoint kept unchanged")
    database.execute("INSERT OR REPLACE INTO state VALUES ('last_poll', ?)", (until.isoformat(),))
    database.commit()


def deliver(database, url, token):
    # This connection uses OS trust roots and never disables HTTPS verification.
    opener = urllib.request.build_opener()
    for _ in range(100):
        rows = database.execute("SELECT id, payload FROM events WHERE sent=0 ORDER BY occurred, rowid LIMIT 100").fetchall()
        result = post_json(opener, url, {"events": [json.loads(row[1]) for row in rows]}, {"Authorization": "Bearer " + token})
        if not result.get("data", {}).get("applied"):
            logging.info("Shadow mode: batch validated, attendance unchanged, local queue retained")
            return
        if not rows:
            return
        database.executemany("UPDATE events SET sent=1 WHERE id=?", [(row[0],) for row in rows])
        database.commit()


def main():
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    url = os.environ["ERP_BRIDGE_URL"].rstrip("/")
    if urllib.parse.urlparse(url).scheme != "https":
        raise ValueError("ERP_BRIDGE_URL must use HTTPS")
    device_url = os.environ["DEVICE_URL"].rstrip("/")
    if urllib.parse.urlparse(device_url).scheme not in ("http", "https"):
        raise ValueError("DEVICE_URL must use HTTP or HTTPS")
    credentials = urllib.request.HTTPPasswordMgrWithDefaultRealm()
    credentials.add_password(None, device_url, os.environ["DEVICE_USERNAME"], os.environ["DEVICE_PASSWORD"])
    opener = urllib.request.build_opener(urllib.request.HTTPDigestAuthHandler(credentials))
    database = open_spool(os.environ.get("BRIDGE_SPOOL", "device-bridge.sqlite3"))
    initial_since = os.environ["BRIDGE_START_AT"]  # Explicit: never silently import old payroll history.
    interval = max(30, int(os.environ.get("BRIDGE_INTERVAL_SECONDS", "60")))
    while True:
        try:
            collect(database, opener, device_url, initial_since)
        except Exception as error:
            logging.error("Local collection failed (%s); checkpoint retained", type(error).__name__)
        try:
            deliver(database, url, os.environ["ERP_BRIDGE_TOKEN"])
        except Exception as error:
            logging.error("ERP delivery failed (%s); events retained for retry", type(error).__name__)
        time.sleep(interval)


if __name__ == "__main__":
    main()
