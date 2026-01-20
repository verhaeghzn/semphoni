# gui-based-sem-control

GUI automation + Reverb/Pusher-compatible WebSocket client for SEM control.

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
# If you're using ngrok, set the host exactly (no DDEV port guessing will happen).
# The code's default is: semphoni-wss.eu.ngrok.io
export REVERB_WS_HOST="semphoni-wss.eu.ngrok.io"
export REVERB_APP_KEY="unnz4mz0qddaghivqq2c"
export REVERB_AUTH_URL="https://semphoni-wss.eu.ngrok.io/client/broadcasting/auth"
export REVERB_CLIENT_KEY="test-tescan-pc-api-key"
export REVERB_CHANNEL="presence-client.1"

# Optional: only set this if you have TLS/cert issues (e.g. DDEV self-signed certs).
# export REVERB_INSECURE_SSL="1"

# Optional: which monitor to capture when taking screenshots
export SEMPC_MONITOR_NUMBER="2"

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
set REVERB_WS_HOST=semphoni-control-server.ddev.site
set REVERB_APP_KEY=unnz4mz0qddaghivqq2c
set REVERB_AUTH_URL=https://semphoni-control-server.ddev.site/client/broadcasting/auth
set REVERB_CLIENT_KEY=test-tescan-pc-api-key
set REVERB_CHANNEL=presence-client.1

REM DDEV uses a self-signed TLS cert by default (needed for wss://...:8443 and https://...:8443).
set REVERB_INSECURE_SSL=1

set SEMPC_MONITOR_NUMBER=2
set SERVER_PASSWORD=hello123

set LOCAL_RELAY_TOKEN=pc1-token-123
set LOCAL_RELAY_HOST=0.0.0.0
set LOCAL_RELAY_PORT=8765
set LOCAL_RELAY_ALLOWLIST=192.168.1.0/24
```

#### Windows (PowerShell): `$env:...`

```powershell
$env:REVERB_WS_HOST="semphoni-control-server.ddev.site"
$env:REVERB_APP_KEY="unnz4mz0qddaghivqq2c"
$env:REVERB_AUTH_URL="https://semphoni-control-server.ddev.site/client/broadcasting/auth"
$env:REVERB_CLIENT_KEY="test-tescan-pc-api-key"
$env:REVERB_CHANNEL="presence-client.1"

# DDEV uses a self-signed TLS cert by default (needed for wss://...:8443 and https://...:8443).
$env:REVERB_INSECURE_SSL="1"

$env:SEMPC_MONITOR_NUMBER="2"
$env:SERVER_PASSWORD="hello123"

$env:LOCAL_RELAY_TOKEN="pc1-token-123"
$env:LOCAL_RELAY_HOST="0.0.0.0"
$env:LOCAL_RELAY_PORT="8765"
$env:LOCAL_RELAY_ALLOWLIST="192.168.1.0/24"
```

### Run

#### Default mode (cloud WebSocket client + optional LAN relay)

```bash
python3 -m sem_control
```

Backwards compatible entrypoint:

```bash
python3 Server/main.py
```

#### REST API mode (opt-in)

```bash
python3 -m sem_control --rest
```

#### Validation / calibration

```bash
python3 -m sem_control --validate
python3 -m sem_control --calibrate
```

## Configuration reference

### Reverb / Pusher-compatible cloud connection (required)

- **`REVERB_WS_URL`**: optional full WS url. If not set, the client constructs a URL from `REVERB_WS_HOST` + `REVERB_APP_KEY`.
- **`REVERB_WS_HOST`**: host/base WS URL (default in code: `semphoni-wss.eu.ngrok.io`)
- **`REVERB_APP_KEY`**: used when constructing the WS URL (default in code: `unnz4mz0qddaghivqq2c`)
- **`REVERB_AUTH_URL`** (**required**): HTTP auth endpoint for subscribing (example: `https://.../broadcasting/auth`)
- **`REVERB_CLIENT_KEY`** (**required**): sent as `X-Client-Key` header when authenticating
- **`REVERB_CHANNEL`**: presence channel name (default: `presence-client.1`)
- **`REVERB_INSECURE_SSL`**: set to `1` to skip TLS verification (useful for self-signed certs, e.g. DDEV)
- **`REVERB_HEARTBEAT_SECONDS`**, **`REVERB_RECONNECT_DELAY_SECONDS`**, **`REVERB_VERSION`**, **`REVERB_LOG_HEARTBEATS`**, **`REVERB_MAX_MESSAGE_BYTES`**: optional tuning flags

### REST API auth

- **`SERVER_PASSWORD`**: password for protected REST endpoints (default is `hello123` if not set). Use a strong value in real deployments.

### Local LAN relay (optional, runs on PC2)

- **`LOCAL_RELAY_TOKEN`**: shared secret clients must provide as header `X-PC1-Token`
- **`LOCAL_RELAY_HOST`**: bind host (default `0.0.0.0`)
- **`LOCAL_RELAY_PORT`**: bind port (default `8765`)
- **`LOCAL_RELAY_ALLOWLIST`**: optional comma-separated IPs/CIDRs (example: `192.168.1.0/24`)
- **`LOCAL_RELAY_MAX_MESSAGE_BYTES`**: max message size (default 1 MiB)
- **`RELAY_OUTBOX_MAX_TOTAL`**, **`RELAY_OUTBOX_MAX_PER_CLIENT`**: queue limits for local→cloud forwarding

### Monitor selection (optional)

- **`SEMPC_MONITOR_NUMBER`**: which monitor index to capture for screenshots (default `2`)

## DDEV note (if you use it)

If `REVERB_AUTH_URL` or `REVERB_WS_URL` points at a `*.ddev.site` hostname **without an explicit port**, the client will automatically try common DDEV router ports (`:8080` / `:8443`) as fallbacks.

If you see TLS errors with `:8443`, set `REVERB_INSECURE_SSL=1` (or install/trust the DDEV CA cert on your machine).

