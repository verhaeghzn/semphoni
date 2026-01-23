# Semphony Device Client

GUI automation + Reverb/Pusher-compatible WebSocket client for device control.

## Quick start

### Install

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

### Configure environment variables

The app reads its configuration from environment variables (and also loads a local `.env` file if present).

#### macOS/Linux (zsh/bash): `export ...`

```bash
# Default host/app settings (change as needed)
export REVERB_WS_HOST="ws.semphoni.multiscale.nl"
export REVERB_APP_ID="626126"
export REVERB_APP_KEY="mtnrfqng7jaloh9uq1gi"
export REVERB_AUTH_URL="https://ws.semphoni.multiscale.nl/client/broadcasting/auth"
export REVERB_CLIENT_KEY="test-tescan-pc-api-key"
export REVERB_CHANNEL="presence-client.1"
export REVERB_APP_SECRET=""

# Optional: only set this if you have TLS/cert issues (e.g. DDEV self-signed certs).
# export REVERB_INSECURE_SSL="1"

# Optional: which monitor to capture when taking screenshots
export SEMPC_MONITOR_NUMBER="2"

# Optional: screenshot encoding (base64 is always used in responses)
# JPEG is the only supported format.
# export SCREENSHOT_JPEG_QUALITY="75"  # 1-100 (default: 75)

# Optional: SEM telemetry mode (vendor SDK)
# Default is "gui" (no vendor metrics). For TESCAN MIRA 3 metrics:
# export SEM_MODE="tescan_mira3"
# export TESCAN_SDK_HOST="127.0.0.1"
# export TESCAN_SDK_PORT="8300"
# export TESCAN_SDK_TIMEOUT_S="2.0"

# REST API password (used for protected REST endpoints)
export SERVER_PASSWORD="hello123"

# Optional: enable the local LAN relay (PC2) and its auth token
export LOCAL_RELAY_TOKEN="pc1-token-123"
export LOCAL_RELAY_HOST="0.0.0.0"
export LOCAL_RELAY_PORT="8765"
export LOCAL_RELAY_ALLOWLIST="192.168.1.0/24"
```

#### Windows (cmd.exe): `set ...`

```bat
REM DDEV (recommended): do NOT include :8080 or :8443 here; the client will try both.
set REVERB_WS_HOST=semphony-control-server.ddev.site
set REVERB_APP_ID=915841
set REVERB_APP_KEY=unnz4mz0qddaghivqq2c
set REVERB_AUTH_URL=https://semphony-control-server.ddev.site/client/broadcasting/auth
set REVERB_CLIENT_KEY=test-tescan-pc-api-key
set REVERB_CHANNEL=presence-client.1
set REVERB_APP_SECRET=

REM DDEV uses a self-signed TLS cert by default (needed for wss://...:8443 and https://...:8443).
set REVERB_INSECURE_SSL=1

set SEMPC_MONITOR_NUMBER=2
set SERVER_PASSWORD=hello123

REM Optional: SEM telemetry mode (vendor SDK)
REM set SEM_MODE=tescan_mira3
REM set TESCAN_SDK_HOST=127.0.0.1
REM set TESCAN_SDK_PORT=8300
REM set TESCAN_SDK_TIMEOUT_S=2.0

set LOCAL_RELAY_TOKEN=pc1-token-123
set LOCAL_RELAY_HOST=0.0.0.0
set LOCAL_RELAY_PORT=8765
set LOCAL_RELAY_ALLOWLIST=192.168.1.0/24
```

#### Windows (PowerShell): `$env:...`

```powershell
$env:REVERB_WS_HOST="semphony-control-server.ddev.site"
$env:REVERB_APP_ID="915841"
$env:REVERB_APP_KEY="unnz4mz0qddaghivqq2c"
$env:REVERB_AUTH_URL="https://semphony-control-server.ddev.site/client/broadcasting/auth"
$env:REVERB_CLIENT_KEY="test-tescan-pc-api-key"
$env:REVERB_CHANNEL="presence-client.1"

# DDEV uses a self-signed TLS cert by default (needed for wss://...:8443 and https://...:8443).
$env:REVERB_INSECURE_SSL="1"

$env:SEMPC_MONITOR_NUMBER="2"
$env:SERVER_PASSWORD="hello123"

# Optional: SEM telemetry mode (vendor SDK)
# $env:SEM_MODE="tescan_mira3"
# $env:TESCAN_SDK_HOST="127.0.0.1"
# $env:TESCAN_SDK_PORT="8300"
# $env:TESCAN_SDK_TIMEOUT_S="2.0"

$env:LOCAL_RELAY_TOKEN="pc1-token-123"
$env:LOCAL_RELAY_HOST="0.0.0.0"
$env:LOCAL_RELAY_PORT="8765"
$env:LOCAL_RELAY_ALLOWLIST="192.168.1.0/24"
```

### Run

#### Default mode (cloud WebSocket client + optional LAN relay)

```bash
python3 -m device_client
```

Backwards compatible entrypoint:

```bash
python3 Server/main.py
```

#### REST API mode (opt-in)

```bash
python3 -m device_client --rest
```

#### Validation / calibration

```bash
python3 -m device_client --validate
python3 -m device_client --calibrate
```

## Proxy/Relay Setup

For environments where one PC (PC2) has internet connectivity and another PC (PC1) is only connected to the local network, the client supports a proxy/relay configuration. PC2 acts as a relay server, forwarding messages between PC1 and the cloud.

See [PROXY_SETUP.md](PROXY_SETUP.md) for detailed setup instructions.

## Configuration reference

### Reverb / Pusher-compatible cloud connection (required)

- **`REVERB_WS_URL`**: optional full WS url. If not set, the client constructs a URL from `REVERB_WS_HOST` + `REVERB_APP_KEY`.
- **`REVERB_WS_HOST`**: host/base WS URL (default in code: `ws.semphoni.multiscale.nl`)
- **`REVERB_WS_PROTOCOL_QUERY`**: optional query string used when constructing the WS URL (default in code: `protocol=7&client=python&version=0.1&flash=false`)
- **`REVERB_APP_ID`**: informational app id (default in code: `626126`)
- **`REVERB_APP_KEY`**: used when constructing the WS URL (default in code: `mtnrfqng7jaloh9uq1gi`)
- **`REVERB_AUTH_URL`** (**required**): HTTP auth endpoint for subscribing (example: `https://.../broadcasting/auth`)
- **`REVERB_CLIENT_KEY`** (**required**): sent as `X-Client-Key` header when authenticating
- **`REVERB_CHANNEL`**: presence channel name (default: `presence-client.1`)
- **`REVERB_WS_ORIGIN`**: optional `Origin` header to send during the WS handshake (some edge/WAF configs require this)
- **`REVERB_WS_USER_AGENT`**: optional `User-Agent` header to send during the WS handshake
- **`REVERB_INSECURE_SSL`**: set to `1` to skip TLS verification (useful for self-signed certs, e.g. DDEV)
- **`REVERB_HEARTBEAT_SECONDS`**, **`REVERB_RECONNECT_DELAY_SECONDS`**, **`REVERB_VERSION`**, **`REVERB_LOG_HEARTBEATS`**, **`REVERB_MAX_MESSAGE_BYTES`**: optional tuning flags

### REST API auth

- **`SERVER_PASSWORD`**: password for protected REST endpoints (default is `hello123` if not set). Use a strong value in real deployments.

### Local LAN relay (optional, runs on PC2)

- **`LOCAL_RELAY_TOKEN`**: shared secret clients must provide as header `X-PC1-Token`. If empty/unset, the relay server is **disabled** (so cloud commands won’t get routed into a “black hole”).
- **`LOCAL_RELAY_HOST`**: bind host (default `0.0.0.0`)
- **`LOCAL_RELAY_PORT`**: bind port (default `8765`)
- **`LOCAL_RELAY_ALLOWLIST`**: optional comma-separated IPs/CIDRs (example: `192.168.1.0/24`)
- **`LOCAL_RELAY_MAX_MESSAGE_BYTES`**: max message size (default 1 MiB)
- **`RELAY_OUTBOX_MAX_TOTAL`**, **`RELAY_OUTBOX_MAX_PER_CLIENT`**: queue limits for local→cloud forwarding

### Monitor selection (optional)

- **`SEMPC_MONITOR_NUMBER`**: which monitor index to capture for screenshots (default `2`)

### Screenshot encoding (optional)

- **`SCREENSHOT_JPEG_QUALITY`**: JPEG quality `1-100` (default `75`)

### SEM telemetry / vendor SDK mode (optional)

By default this project controls the SEM via GUI automation only. If the SEM exposes a vendor SDK / remote control interface, you can enable telemetry.

- **`SEM_MODE`**:
  - `gui` (default): no vendor telemetry; `get_metrics` reports "not supported"
  - `tescan_mira3`: enable TESCAN MIRA 3 metrics via the local SharkSEM interface
- **`TESCAN_SDK_HOST`**: host of the SharkSEM server (default `127.0.0.1`)
- **`TESCAN_SDK_PORT`**: port of the SharkSEM control channel (default `8300`)
- **`TESCAN_SDK_TIMEOUT_S`**: socket timeout in seconds (default `2.0`)

#### `get_metrics` (cloud / Reverb server-command)

When `SEM_MODE=tescan_mira3`, the client supports a `get_metrics` server-command (aliases: `getMetrics`, `get-metrics`).

It returns a JSON payload with key fields:

- **beam kV**: `beam_kv`
- **beam current**: `beam_current_a`
- **stage**: `stage.x`, `stage.y`, `stage.z` (also includes rotation/tilt as `stage.r`, `stage.t`)
- **working distance + Z**: `working_distance.wd`, `working_distance.z`
- **beam on**: `beam_on`
- **pump / vacuum status**: `pump.status` and `pump.status_code`

If the SDK cannot be reached, the command returns `ok=false` with a message and a payload containing `supported=false`.

#### `GET /metrics` (REST)

If you run the REST server (`python3 -m device_client --rest`), there is also:

- `GET /metrics` (password protected): returns the same JSON as `get_metrics`

## DDEV note (if you use it)

If `REVERB_AUTH_URL` or `REVERB_WS_URL` points at a `*.ddev.site` hostname **without an explicit port**, the client will automatically try common DDEV router ports (`:8080` / `:8443`) as fallbacks.

If you see TLS errors with `:8443`, set `REVERB_INSECURE_SSL=1` (or install/trust the DDEV CA cert on your machine).

