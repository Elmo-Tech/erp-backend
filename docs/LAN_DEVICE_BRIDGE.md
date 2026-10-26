# Office fingerprint terminal → hosted ERP

The hosted server cannot reach a private address such as `192.168.1.50` on the
office LAN. A public server initiating polling does **not** bypass office NAT.

## Recommended topology

```text
Fingerprint terminal <-- LAN / read-only ISAPI queries --> Office bridge
                                                       |
                                                       +-- outbound HTTPS 443 --> ERP
```

Use an always-on office Windows/Linux computer or mini-PC with Python 3. The bridge
only queries `/ISAPI/AccessControl/AcsEvent`; it never changes users, enrolled
fingerprints, access rights, door settings, or the existing attendance software.
No port-forwarding, public terminal address, or new inbound office firewall rule
is needed. Configure a reserved/static LAN address for the terminal.

## Setup and shadow verification

1. Register the terminal in ERP and link its **existing** employee numbers to ERP
   employees. Do not use Send all employees when preserving existing numbering.
2. On the hosted server run `php artisan devices:bridge DEVICE_ID`. This disables
   cloud polling for that terminal, enables **shadow mode**, and prints a unique
   bridge token once. Keep that token on the bridge, not in a repository.
3. Set the following environment variables on the office computer:

   ```text
   DEVICE_URL=http://192.168.1.50
   DEVICE_USERNAME=<read-access terminal account>
   DEVICE_PASSWORD=<terminal password>
   ERP_BRIDGE_URL=https://erp.example.com/api/v1/device-bridge/DEVICE_ID/events
   ERP_BRIDGE_TOKEN=<one-time token from the command>
   BRIDGE_START_AT=2026-10-25T00:00:00+03:00
   BRIDGE_SPOOL=/absolute/private/path/device-bridge.sqlite3
   BRIDGE_INTERVAL_SECONDS=60
   ```

   Choose the actual go-live date/time and office offset, rather than copying the
   example date. The office computer and device clocks must agree.
4. Run `python3 ops/device_bridge.py` (Windows: `python ops/device_bridge.py`).
   Shadow batches are validated but **do not write attendance**. Events remain in
   the local SQLite queue until application is enabled. Compare sample arrival and
   departure times and employee numbers against the existing software.
5. Enable application only after verification:
   `php artisan devices:bridge DEVICE_ID --activate`.
   The token stays unchanged; queued events are imported in timestamp order.
6. Run the bridge as a Windows service/Task Scheduler startup task or a systemd
   service. Restrict access to its environment and spool directory to the service
   account. Keep the spool persistent across upgrades/restarts.

Failed internet delivery keeps the queue intact. Failed polling does not advance
the checkpoint. Polling uses pagination and a ten-minute overlap; the ERP's unique
per-device event ID makes retries idempotent. Event data excludes requested pictures
and fingerprint templates. No terminal admin credential is sent to the ERP by the
bridge. `devices:bridge DEVICE_ID --rotate-token` revokes the previous bridge key.
Running without `--activate` returns to shadow mode.

## Alternative: private VPN

A site-to-site WireGuard tunnel, or a Tailscale subnet router inside the office,
can let the ERP reach only the terminal's LAN address. Route/ACL only that terminal
and ISAPI port; do not route the entire office network or change its default
gateway. This permits existing direct integration screens, including authorized
user provisioning. The bridge is preferable for read-only attendance ingestion
without routing access into the office.

## Required site details

- Terminal model/firmware and ISAPI support.
- Availability and OS of an always-on office computer.
- Existing employee numbering and one sample event payload.
- Current check-in/out rules: the existing integration pairs the first two valid
  daily punches. Confirm this before enabling payroll-affecting attendance.

The bridge code and simulated tests do not establish hardware compatibility;
shadow verification on the actual terminal is required before activation.
