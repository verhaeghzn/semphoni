# Proxy Setup Guide

This guide explains how to set up the SEMPhoni client in a proxy/relay configuration where one PC (PC2) has internet connectivity and another PC (PC1) is only connected to the local network.

## Architecture Overview

The proxy setup uses a **local LAN relay** feature built into the SEMPhoni client:

- **PC2** (Internet-connected): Runs the main client that connects to the cloud WebSocket server AND acts as a relay server for local clients
- **PC1** (Local-only): Connects to PC2's relay server over the local network, and all its messages are forwarded through PC2 to/from the cloud

```
┌─────────┐         ┌─────────┐         ┌─────────┐
│   PC1   │────────▶│   PC2   │────────▶│  Cloud  │
│(Local)  │  LAN    │(Internet)│  WSS   │  Server │
└─────────┘         └─────────┘         └─────────┘
```

## Setup Instructions

### Step 1: Configure PC2 (Internet-connected relay server)

On PC2, set the following environment variables to enable the relay server:

#### macOS/Linux (zsh/bash):

```bash
# Cloud connection settings (required)
export REVERB_WS_HOST="ws.semphoni.multiscale.nl"
export REVERB_APP_ID="626126"
export REVERB_APP_KEY="mtnrfqng7jaloh9uq1gi"
export REVERB_AUTH_URL="https://ws.semphoni.multiscale.nl/client/broadcasting/auth"
export REVERB_CLIENT_KEY="test-tescan-pc-api-key"
export REVERB_CHANNEL="presence-client.1"

# Enable the local LAN relay server
export LOCAL_RELAY_TOKEN="pc1-token-123"  # Shared secret - use a strong value!
export LOCAL_RELAY_HOST="0.0.0.0"        # Listen on all interfaces
export LOCAL_RELAY_PORT="8765"            # Default port
export LOCAL_RELAY_ALLOWLIST="192.168.1.0/24"  # Optional: restrict to local subnet

# Other optional settings
export SEMPC_MONITOR_NUMBER="2"
export SERVER_PASSWORD="hello123"
```

#### Windows (cmd.exe):

```bat
set REVERB_WS_HOST=ws.semphoni.multiscale.nl
set REVERB_APP_ID=626126
set REVERB_APP_KEY=mtnrfqng7jaloh9uq1gi
set REVERB_AUTH_URL=https://ws.semphoni.multiscale.nl/client/broadcasting/auth
set REVERB_CLIENT_KEY=test-tescan-pc-api-key
set REVERB_CHANNEL=presence-client.1

set LOCAL_RELAY_TOKEN=pc1-token-123
set LOCAL_RELAY_HOST=0.0.0.0
set LOCAL_RELAY_PORT=8765
set LOCAL_RELAY_ALLOWLIST=192.168.1.0/24

set SEMPC_MONITOR_NUMBER=2
set SERVER_PASSWORD=hello123
```

#### Windows (PowerShell):

```powershell
$env:REVERB_WS_HOST="ws.semphoni.multiscale.nl"
$env:REVERB_APP_ID="626126"
$env:REVERB_APP_KEY="mtnrfqng7jaloh9uq1gi"
$env:REVERB_AUTH_URL="https://ws.semphoni.multiscale.nl/client/broadcasting/auth"
$env:REVERB_CLIENT_KEY="test-tescan-pc-api-key"
$env:REVERB_CHANNEL="presence-client.1"

$env:LOCAL_RELAY_TOKEN="pc1-token-123"
$env:LOCAL_RELAY_HOST="0.0.0.0"
$env:LOCAL_RELAY_PORT="8765"
$env:LOCAL_RELAY_ALLOWLIST="192.168.1.0/24"

$env:SEMPC_MONITOR_NUMBER="2"
$env:SERVER_PASSWORD="hello123"
```

**Important notes for PC2:**
- `LOCAL_RELAY_TOKEN` must be set to a non-empty value, otherwise the relay server will be disabled
- `LOCAL_RELAY_HOST="0.0.0.0"` makes the relay listen on all network interfaces
- `LOCAL_RELAY_ALLOWLIST` is optional but recommended for security - it restricts which IPs can connect
- Find PC2's local IP address (e.g., `192.168.1.100`) - you'll need this for PC1

### Step 2: Start PC2

Run the client on PC2:

```bash
python3 -m sem_control
```

You should see log messages indicating:
- The cloud WebSocket connection is established
- The local relay server is listening: `"local_relay_listening", host="0.0.0.0", port=8765`

### Step 3: Configure PC1 (Local-only client)

On PC1, you need to connect to PC2's relay server. The client needs to be configured to connect via the relay instead of directly to the cloud.

**Note:** The current client implementation connects directly to the cloud. To use the relay, you would need to implement a client that connects to `ws://<pc2-ip>:8765` with the appropriate headers. See the "Testing the Connection" section below for a working example using the PC1 simulator.

For a full client implementation, you would need to:

1. Connect to `ws://<pc2-ip>:8765` (where `<pc2-ip>` is PC2's local IP address)
2. Include the header `X-PC1-Token: <token>` (matching `LOCAL_RELAY_TOKEN` from PC2)
3. Optionally include `X-Relay-Client-Id: <client_id>` to set a specific client ID
4. Send/receive messages using the relay protocol (see `relay_gateway.py` for details)

### Step 4: Testing the Connection

You can test the relay setup using the included PC1 simulator:

**On PC1:**

```bash
# Set the token (must match PC2's LOCAL_RELAY_TOKEN)
export LOCAL_RELAY_TOKEN="pc1-token-123"

# Run the simulator, pointing to PC2's IP address
python3 -m sem_control.tools.pc1_simulator --host 192.168.1.100
```

Replace `192.168.1.100` with PC2's actual local IP address.

The simulator will:
1. Connect to PC2's relay server
2. Authenticate using the token
3. Send a ping and status request
4. Display any messages received from the cloud

If successful, you should see:
- A welcome message with your client_id
- A pong response to the ping
- A status response showing cloud connection status

## Configuration Reference

### PC2 Relay Server Settings

| Environment Variable | Description | Default | Required |
|---------------------|-------------|---------|----------|
| `LOCAL_RELAY_TOKEN` | Shared secret for authentication. Must match on PC1 and PC2. | (empty) | **Yes** (if using relay) |
| `LOCAL_RELAY_HOST` | IP address to bind the relay server to | `0.0.0.0` | No |
| `LOCAL_RELAY_PORT` | Port for the relay server | `8765` | No |
| `LOCAL_RELAY_ALLOWLIST` | Comma-separated IPs/CIDRs allowed to connect (e.g., `192.168.1.0/24,10.0.0.1`) | (empty = allow all) | No |
| `LOCAL_RELAY_MAX_MESSAGE_BYTES` | Maximum message size in bytes | `1048576` (1 MiB) | No |
| `RELAY_OUTBOX_MAX_TOTAL` | Maximum total queued messages from local→cloud | `1000` | No |
| `RELAY_OUTBOX_MAX_PER_CLIENT` | Maximum queued messages per client | `100` | No |

### Security Considerations

1. **Token Security**: Use a strong, random value for `LOCAL_RELAY_TOKEN`. Don't use the example value in production.

2. **Network Isolation**: Use `LOCAL_RELAY_ALLOWLIST` to restrict which IPs can connect to the relay server. This prevents unauthorized access from other devices on the network.

3. **Firewall**: Ensure that:
   - PC2 allows incoming connections on port 8765 (or your chosen port)
   - PC1 can reach PC2 on the local network
   - PC2 can reach the cloud WebSocket server

## Troubleshooting

### PC2 relay server not starting

- **Check**: Is `LOCAL_RELAY_TOKEN` set? The relay is disabled if the token is empty.
- **Check**: Look for log messages like `"local_relay_listening"` or `"local_relay_disabled"`

### PC1 cannot connect to PC2

- **Check**: Verify PC2's IP address is correct
- **Check**: Ensure PC2's firewall allows connections on port 8765
- **Check**: Verify `LOCAL_RELAY_TOKEN` matches on both PCs
- **Check**: If `LOCAL_RELAY_ALLOWLIST` is set, ensure PC1's IP is in the allowed range
- **Check**: Look for log messages on PC2 like `"local_relay_rejected_ip"` or `"local_relay_rejected_auth"`

### Messages not being forwarded

- **Check**: Verify PC2 is connected to the cloud (check logs for `"cloud_connected": true`)
- **Check**: Ensure the client_id in relay messages matches the client_id of the connected PC1 client
- **Check**: Look for `"local_relay_route_miss"` messages on PC2, which indicate a message was routed to a non-existent client

### Finding PC2's IP Address

**On PC2:**

- **macOS/Linux**: Run `ip addr show` or `ifconfig` and look for your network interface
- **Windows**: Run `ipconfig` and look for "IPv4 Address" under your active network adapter

## Protocol Details

The relay uses a simple JSON-over-WebSocket protocol:

### Connection

- **URL**: `ws://<pc2-ip>:<port>` (default port: 8765)
- **Headers**:
  - `X-PC1-Token: <token>` (required)
  - `X-Relay-Client-Id: <client_id>` (optional)

### Message Types

**From PC1 to PC2:**
- `{"type": "ping", "msg_id": "..."}` → `{"type": "pong", "msg_id": "..."}`
- `{"type": "status"}` → `{"type": "status", "client_id": "...", "cloud_connected": true/false}`
- `{"type": "to_cloud", "msg_id": "...", "event": "...", "data": {...}}` → `{"type": "ack", "msg_id": "...", "status": "queued"}`

**From PC2 to PC1:**
- `{"type": "welcome", "client_id": "...", "cloud_connected": true/false}` (on connect)
- `{"type": "from_cloud", "msg_id": "...", "event": "...", "data": {...}}` (cloud messages routed to this client)

For more details, see `sem_control/relay_gateway.py`.
