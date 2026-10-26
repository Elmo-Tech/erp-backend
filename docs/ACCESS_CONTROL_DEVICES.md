# Access Control Device Integration (Hikvision DS-K1T8003MF)

The ERP can read punch events from a Hikvision fingerprint / card terminal and turn
them into attendance records. This document explains how to configure the ERP
side and how to configure the terminal itself.

The terminal in question is a **DS-K1T8003MF** (fingerprint + Mifare card,
12V/1A). The same integration works for the other members of the DS-K1T8003
series.

---

## 1. How the two systems talk to each other

The terminal speaks **ISAPI** (Hikvision's REST API over HTTP) using the same
credentials as its web page. Two ingestion modes are supported.

### Mode A — Polling (recommended, default)

```
ERP scheduler  ──every 5 min──▶  POST /ISAPI/AccessControl/AcsEvent
                                  on the device
```

* The ERP reads the device event log and writes the new events into
  `device_access_events`.
* A hosted ERP cannot reach an office-private address through NAT on its own.
  For a terminal on a separate office LAN, use the outbound bridge described in
  [LAN_DEVICE_BRIDGE.md](LAN_DEVICE_BRIDGE.md), or a restricted private VPN.
* The device only needs to be reachable from the server that runs the backend.

**Use this mode unless the terminal can reach the ERP server directly.**

### Mode B — Push (optional)

```
Terminal  ──HTTP POST──▶  {APP_URL}/api/v1/device-events/{webhook_token}
```

* The device is configured to POST every event to the ERP.
* The token in the URL is the credential — it is generated per device and
  stored encrypted-ish (it is a random 48-character hex string).
* The terminal must be able to reach the ERP server over the internet, and the
  ERP must have a valid (not self-signed) TLS certificate, because Hikvision
  terminals refuse to push to self-signed HTTPS endpoints.

Both modes write into the exact same pipeline, so they can be combined.

---

## 2. Terminal-side configuration (on the device itself)

Open the terminal web page at `http://<device-ip>/` and sign in as an
administrator.

| Step | Where | What to set |
| --- | --- | --- |
| 1 | `Comm. → Network` | Static IP address, gateway, DNS. The ERP must be able to reach this IP. |
| 2 | `Comm. → EHome` | **Leave EHome disabled.** The ERP does not use the EHome/ISUP protocol. |
| 3 | `System → Time` | Set the date, time and timezone. The device reports wall-clock time and the ERP stores it as-is, so the device clock must match the office clock. |
| 4 | `User → New` (or the ERP) | Create the people and set their **ID (Employee ID)** to the same value the ERP uses. |
| 5 | `User → New` | Set the card number if the employees use cards. |
| 6 | Device | Enroll the fingerprint **on the device** (it cannot be created remotely). |

### The employee number — the one thing that must line up

The terminal reports every event with the `employeeNo` (called *ID (Employee
ID)* in the device menu) of the person who authenticated. The ERP matches that
number against `device_users.device_employee_no`.

By default the ERP uses **the ERP user id** as the employee number. So:

```
ERP user id 7  ──▶  written to the terminal as employeeNo "7"
                    ──▶  terminal reports employeeNo "7"
                    ──▶  ERP maps "7" back to user id 7
```

If you want a different number (for example the HR file number), set
`users.employee_no` on the employee — the ERP prefers it over the user id.

If your terminal already has people enrolled with their own numbering, either
re-number them on the device or set the matching `employee_no` in the ERP.

---

## 3. ERP-side configuration

### 3.1 Environment variables (`.env`)

```dotenv
# Poll the terminals for new access events.
DEVICE_POLLING_ENABLED=true
DEVICE_POLL_INTERVAL_MINUTES=5
DEVICE_POLL_LOOKBACK_MINUTES=60

# Public endpoint the terminal can push its events to.
DEVICE_WEBHOOK_ENABLED=true
DEVICE_WEBHOOK_RATE_LIMIT=120

# ISAPI client behaviour.
DEVICE_ISAPI_TIMEOUT=15
DEVICE_ISAPI_CONNECT_TIMEOUT=5
# Keep false for terminals with a self-signed certificate.
DEVICE_ISAPI_VERIFY_TLS=false

# How an employee is verified on the terminal:
# fpOrCard (default) | fp | card | faceOrFpOrCardOrPw
DEVICE_USER_VERIFY_MODE=fpOrCard
# Door rights and group sent to the terminal.
DEVICE_DOOR_RIGHT=1
DEVICE_BELONG_GROUP=1
# Validity window of the provisioned account.
DEVICE_VALIDITY_YEARS=10

# Prefix for the employee number written to the terminal.
DEVICE_EMPLOYEE_NO_PREFIX=
# Fall back to the ERP user id when the employee has no employee_no.
DEVICE_EMPLOYEE_NO_FALLBACK_USER_ID=true
```

Then run:

```bash
php artisan migrate
php artisan db:seed --class=AdminUserSeeder   # creates the manage_devices permission
```

### 3.2 Register the terminal

**From the UI**

1. Go to **Settings → Access Devices** (visible for admins and for anyone with
   the `manage_devices` permission).
2. Press **Add device** and fill in:
   * **Name** — e.g. `Main Entrance`
   * **IP address** — the static IP of the terminal
   * **Port** — `80` for HTTP, `443` for HTTPS
   * **Username / Password** — the administrator credentials of the terminal web
     page (stored encrypted in the database)
   * **Model** — `DS-K1T8003MF` (informational)
3. Save.

**From the CLI** (useful for the first setup):

```bash
php artisan devices:probe <device-ip> --username=admin --password=secret
```

The probe prints the device model, serial number and firmware, and lists the
people currently stored on the terminal.

### 3.3 Send the employees to the terminal

Press **Send all employees** in the device details panel, or:

```bash
php artisan devices:probe <device-ip> --push-users
```

For each employee the ERP writes:

* `employeeNo` — the employee number (see section 2)
* `name`
* `userVerifyMode` — `fpOrCard` by default, so the employee can use either
* `doorRight` / `belongGroup` — the access rights
* a validity window of `DEVICE_VALIDITY_YEARS`

The fingerprint itself is **not** transferred — it is enrolled on the terminal.
The ERP reads back the fingerprint status so the UI can show whether the
employee has one.

### 3.4 Choose the ingestion mode

**Polling** is on by default. The scheduler runs:

```
Schedule::job(new SyncDeviceEventsJob)->everyFiveMinutes()
```

Check that the scheduler and the queue worker are running:

```bash
php artisan schedule:work     # or the cron entry from deploy.sh
php artisan queue:work
```

To poll manually:

```bash
php artisan devices:sync
```

**Push** — press **Enable push** in the device details panel. The ERP calls
`PUT /ISAPI/Event/notification/httpHosts/1` on the terminal with the webhook
URL, and the terminal then POSTs every event to the ERP. The URL that was
configured is displayed in the panel.

> Push requires the terminal to reach the ERP server over the internet and the
> ERP to serve a valid TLS certificate. If either is not the case, stay on
> polling.

### 3.5 Verify

```bash
# read the event log and check that punches become attendance
php artisan devices:sync

# inspect what the ERP recorded
php artisan tinker
>>> App\Models\DeviceAccessEvent::latest()->take(5)->get(['device_employee_no','occurred_at','is_authorized']);
>>> App\Models\Attendance::where('source','device')->latest()->take(5)->get(['user_id','date','clock_in','clock_out']);
```

---

## 4. How a punch becomes an attendance record

```
terminal event
    │
    ├─► device_access_events          every attempt, granted or rejected (audit trail)
    │
    └─► authorised + mapped employee?
            │
            ├─ first punch of the day  ──► attendance.clock_in
            ├─ second punch of the day ──► attendance.clock_out
            └─ further punches          ──► recorded only, no attendance change
```

Rules that matter:

* **The earliest punch wins for the clock-in.** If the employee also starts the
  work timer from the web app, the earlier of the two times is kept.
* **The device can close the day.** If the employee never stops the web timer,
  the second punch from the terminal fills in `clock_out`.
* **Rejected attempts never create attendance** — they are only logged.
* **Every event is idempotent.** The event carries a serial number (or a hash
  of its content when the firmware does not send one), so a retried webhook or
  an overlapping poll window cannot double count.
* **Late minutes, grace days and deductions are unchanged.** The punch simply
  provides `clock_in`, and the existing `AttendanceService` does the rest, so
  the device and the web app behave identically.

---

## 5. API reference

### Device administration (requires `manage_devices`)

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/v1/devices` | List the terminals |
| POST | `/api/v1/devices` | Register a terminal |
| GET | `/api/v1/devices/{id}` | One terminal with its linked employees |
| PUT | `/api/v1/devices/{id}` | Update a terminal |
| DELETE | `/api/v1/devices/{id}` | Remove a terminal |
| POST | `/api/v1/devices/{id}/test` | Check the connection and the credentials |
| POST | `/api/v1/devices/{id}/poll` | Read the event log right now |
| POST | `/api/v1/devices/{id}/users/push` | Send every employee to the terminal |
| GET | `/api/v1/devices/{id}/users/pull` | Read the people stored on the terminal |
| POST | `/api/v1/devices/{id}/users` | Send one employee to the terminal |
| DELETE | `/api/v1/devices/{id}/users/{deviceUserId}` | Unlink an employee |
| GET | `/api/v1/devices/{id}/events` | The recorded events |
| POST | `/api/v1/devices/{id}/webhook/enable` | Configure the terminal to push |
| POST | `/api/v1/devices/{id}/webhook/disable` | Stop the push |

### Device push (public, token authenticated)

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/v1/device-events/{token}` | Receive an event from a terminal |

Rate limited to `DEVICE_WEBHOOK_RATE_LIMIT` requests per minute per token.

---

## 6. Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `The device could not be reached` | Wrong IP or port, or the server has no route to the LAN. Run `ping <device-ip>` and `curl -I http://<device-ip>/ISAPI/System/deviceInfo` from the server. |
| `The device rejected the credentials` | The terminal was activated with a different admin password, or the account lacks the administrator role. Reset it from the terminal menu. |
| `The device does not support this ISAPI endpoint` | The firmware is too old. Update the terminal firmware, or the endpoint is only used for a feature you do not need. |
| No attendance after a punch | The punch was not linked: check **Settings → Access Devices → Employees on this device** for the employee number. A punch of an unmapped employee is stored but ignored. |
| Attendance in the wrong time zone | The terminal clock is wrong. Set it under `System → Time` on the device, including the timezone. |
| Duplicate attendance | Should not happen — events are idempotent. If it does, check that only one ERP instance is polling the same device. |
| The terminal does not push | The terminal cannot reach the ERP over the internet, or the ERP uses a self-signed certificate. Switch to polling. |

---

## 7. Data model

| Table | Purpose |
| --- | --- |
| `devices` | One row per terminal: address, credentials, health, webhook token. |
| `device_users` | Mapping between an ERP user and the employee number used on a terminal. |
| `device_access_events` | Every authentication attempt, granted or rejected, with the raw payload. |
| `attendances.source` | `web`, `device` or `manual`. |
| `attendances.device_id` | The terminal that produced the record. |
| `attendances.external_id` | The device event that produced the record. |
| `users.employee_no` | The employee number written to the terminal. |

Fingerprint templates are never stored in the ERP — they stay on the terminal.
