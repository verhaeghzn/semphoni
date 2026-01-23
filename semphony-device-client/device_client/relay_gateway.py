"""
Local LAN WebSocket relay/gateway for Semphony.

This module lets PC2 act as a LAN-only relay for additional "worker" clients
(PC1 and potentially more) that cannot access the internet directly.

High-level behavior:
- PC2 maintains ONE persistent cloud WebSocket (Reverb/Pusher) connection.
- Local LAN clients connect to PC2 via ws://<pc2>:8765.
- Cloud -> PC2 messages can be routed to a specific local client by including:

  data.relay = {"client_id": "<local-client-id>", "msg_id": "<id-optional>"}

- Local -> cloud messages are forwarded by PC2, with PC2 injecting the same
  relay envelope into the outgoing cloud message's `data` field.

Local (LAN) protocol (JSON messages):
- Auth: clients must send `X-PC1-Token: <token>` header, matching env
  `LOCAL_RELAY_TOKEN`, otherwise the connection is closed with code 1008.
- Optional: `X-Relay-Client-Id: <client_id>` header. If omitted, PC2 assigns one.

Message types:
- {"type":"ping","msg_id":"..."} -> {"type":"pong","msg_id":"..."}
- {"type":"status"} ->
    {"type":"status","client_id":"...","cloud_connected":true/false}
- {"type":"to_cloud","msg_id":"...","event":"client-command-result","data":{...}} ->
    {"type":"ack","msg_id":"...","status":"queued"}
  (or {"type":"error",...} on failure)

Cloud-originated delivery to local clients uses:
- {"type":"from_cloud","msg_id":"...","event":"server-command","data":{...}}
"""

from __future__ import annotations

import asyncio
import ipaddress
import json
import logging
import os
import uuid
from dataclasses import dataclass
from typing import Any, Awaitable, Callable, Dict, List, Optional, Sequence

logger = logging.getLogger(__name__)


def _json_log(event: str, **fields: Any) -> None:
    payload = {"event": event, **fields}
    try:
        logger.info("%s", json.dumps(payload, separators=(",", ":"), sort_keys=True))
    except Exception:
        logger.info("event=%s fields=%r", event, fields)


def _parse_allowlist(raw: str) -> List[ipaddress._BaseNetwork]:  # type: ignore[attr-defined]
    """
    Parse comma-separated IPs/CIDRs into ipaddress networks.
    Examples:
      - "192.168.1.10"
      - "192.168.1.0/24,10.0.0.0/8"
    """
    out: List[ipaddress._BaseNetwork] = []  # type: ignore[attr-defined]
    for part in [p.strip() for p in raw.split(",") if p.strip()]:
        try:
            if "/" in part:
                out.append(ipaddress.ip_network(part, strict=False))
            else:
                # single IP => /32 or /128 network
                ip = ipaddress.ip_address(part)
                out.append(ipaddress.ip_network(ip.exploded + ("/32" if ip.version == 4 else "/128"), strict=False))
        except Exception as e:
            raise ValueError(f"Invalid allowlist entry: {part!r}") from e
    return out


def _ip_allowed(remote_ip: str, allowlist: Sequence[ipaddress._BaseNetwork]) -> bool:  # type: ignore[attr-defined]
    if not allowlist:
        return True
    try:
        ip = ipaddress.ip_address(remote_ip)
    except Exception:
        return False
    return any(ip in net for net in allowlist)


@dataclass(frozen=True)
class RelayConfig:
    host: str = "0.0.0.0"
    port: int = 8765
    token: str = ""
    allowlist_raw: str = ""
    max_message_bytes: int = 1 * 1024 * 1024

    @staticmethod
    def from_env() -> "RelayConfig":
        host = (os.getenv("LOCAL_RELAY_HOST") or "0.0.0.0").strip()
        port = int(os.getenv("LOCAL_RELAY_PORT") or "8765")
        token = (os.getenv("LOCAL_RELAY_TOKEN") or "").strip()
        allowlist_raw = (os.getenv("LOCAL_RELAY_ALLOWLIST") or "").strip()
        max_message_bytes = int(os.getenv("LOCAL_RELAY_MAX_MESSAGE_BYTES") or str(1 * 1024 * 1024))
        return RelayConfig(
            host=host,
            port=port,
            token=token,
            allowlist_raw=allowlist_raw,
            max_message_bytes=max_message_bytes,
        )

    @property
    def allowlist(self) -> List[ipaddress._BaseNetwork]:  # type: ignore[attr-defined]
        return _parse_allowlist(self.allowlist_raw) if self.allowlist_raw else []


@dataclass
class LocalClientSession:
    client_id: str
    remote_ip: str
    ws: Any
    send_lock: asyncio.Lock


EnqueueToCloudFn = Callable[[str, str, str, Dict[str, Any]], Awaitable[Dict[str, Any]]]
CloudConnectedFn = Callable[[], bool]


class RelayGateway:
    def __init__(
        self,
        cfg: RelayConfig,
        *,
        enqueue_to_cloud: EnqueueToCloudFn,
        cloud_connected: CloudConnectedFn,
    ) -> None:
        self._cfg = cfg
        self._allowlist = cfg.allowlist
        self._enqueue_to_cloud = enqueue_to_cloud
        self._cloud_connected = cloud_connected

        self._server: Any = None
        self._sessions: Dict[str, LocalClientSession] = {}
        self._sessions_lock = asyncio.Lock()

        self._messages_from_cloud = 0
        self._messages_to_cloud = 0

    async def start(self) -> None:
        import websockets  # type: ignore

        if not self._cfg.token:
            _json_log(
                "local_relay_token_missing",
                message="LOCAL_RELAY_TOKEN is empty; all local connections will be rejected",
            )

        self._server = await websockets.serve(
            self._handle_client,
            self._cfg.host,
            self._cfg.port,
            max_size=self._cfg.max_message_bytes,
            ping_interval=20,
            ping_timeout=20,
        )
        _json_log("local_relay_listening", host=self._cfg.host, port=self._cfg.port)

    async def stop(self) -> None:
        if self._server is not None:
            self._server.close()
            await self._server.wait_closed()
            self._server = None

        async with self._sessions_lock:
            sessions = list(self._sessions.values())
            self._sessions.clear()
        for s in sessions:
            try:
                await s.ws.close(code=1001, reason="server shutting down")
            except Exception:
                pass

    async def send_from_cloud(self, client_id: str, *, msg_id: str, event: str, data: Dict[str, Any]) -> bool:
        async with self._sessions_lock:
            sess = self._sessions.get(client_id)
        if not sess:
            _json_log("local_relay_route_miss", client_id=client_id, event=event)
            return False

        self._messages_from_cloud += 1
        payload = {"type": "from_cloud", "msg_id": msg_id, "event": event, "data": data}
        async with sess.send_lock:
            await sess.ws.send(json.dumps(payload))
        return True

    async def _handle_client(self, ws: Any) -> None:
        # The protocol type differs slightly between websockets versions; keep it `Any`.
        remote_ip = "unknown"
        try:
            remote = getattr(ws, "remote_address", None)
            if isinstance(remote, (tuple, list)) and remote:
                remote_ip = str(remote[0])
        except Exception:
            pass

        # Allowlist
        if not _ip_allowed(remote_ip, self._allowlist):
            _json_log("local_relay_rejected_ip", remote_ip=remote_ip)
            try:
                await ws.close(code=1008, reason="forbidden")
            finally:
                return

        # Token auth
        token = ""
        try:
            token = str(ws.request_headers.get("X-PC1-Token", "")).strip()
        except Exception:
            token = ""
        if not self._cfg.token or token != self._cfg.token:
            _json_log("local_relay_rejected_auth", remote_ip=remote_ip)
            try:
                await ws.close(code=1008, reason="unauthorized")
            finally:
                return

        # Client identity
        client_id = ""
        try:
            client_id = str(ws.request_headers.get("X-Relay-Client-Id", "")).strip()
        except Exception:
            client_id = ""
        if not client_id:
            client_id = str(uuid.uuid4())

        sess = LocalClientSession(client_id=client_id, remote_ip=remote_ip, ws=ws, send_lock=asyncio.Lock())
        async with self._sessions_lock:
            # Kick any existing session with same client_id (simple last-wins behavior)
            old = self._sessions.get(client_id)
            self._sessions[client_id] = sess

        if old:
            try:
                await old.ws.close(code=1000, reason="replaced")
            except Exception:
                pass

        _json_log("local_relay_connected", client_id=client_id, remote_ip=remote_ip)
        try:
            async with sess.send_lock:
                await ws.send(
                    json.dumps(
                        {
                            "type": "welcome",
                            "client_id": client_id,
                            "cloud_connected": bool(self._cloud_connected()),
                        }
                    )
                )

            async for raw in ws:
                await self._handle_local_message(sess, raw)
        except Exception as e:
            _json_log("local_relay_error", client_id=client_id, error=str(e))
        finally:
            async with self._sessions_lock:
                # Only remove if it's still the current session (may have been replaced).
                if self._sessions.get(client_id) is sess:
                    self._sessions.pop(client_id, None)
            _json_log("local_relay_disconnected", client_id=client_id, remote_ip=remote_ip)

    async def _handle_local_message(self, sess: LocalClientSession, raw: Any) -> None:
        if not isinstance(raw, str):
            # For now, require JSON text messages.
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "error", "code": "non_text", "message": "Text JSON required"}))
            return

        try:
            msg = json.loads(raw)
        except Exception:
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "error", "code": "invalid_json", "message": "Invalid JSON"}))
            return

        if not isinstance(msg, dict):
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "error", "code": "invalid_message", "message": "JSON object required"}))
            return

        mtype = str(msg.get("type", "")).strip()
        msg_id = str(msg.get("msg_id", "")).strip()

        if mtype == "ping":
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "pong", "msg_id": msg_id}))
            return

        if mtype == "status":
            async with sess.send_lock:
                await sess.ws.send(
                    json.dumps(
                        {
                            "type": "status",
                            "client_id": sess.client_id,
                            "cloud_connected": bool(self._cloud_connected()),
                            "messages_to_cloud": int(self._messages_to_cloud),
                            "messages_from_cloud": int(self._messages_from_cloud),
                        }
                    )
                )
            return

        if mtype != "to_cloud":
            async with sess.send_lock:
                await sess.ws.send(
                    json.dumps(
                        {
                            "type": "error",
                            "msg_id": msg_id,
                            "code": "unknown_type",
                            "message": f"Unknown type: {mtype}",
                        }
                    )
                )
            return

        event = str(msg.get("event", "")).strip()
        data = msg.get("data") or {}
        if not event:
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "error", "msg_id": msg_id, "code": "missing_event"}))
            return
        if not msg_id:
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "error", "code": "missing_msg_id", "message": "msg_id required"}))
            return
        if not isinstance(data, dict):
            async with sess.send_lock:
                await sess.ws.send(json.dumps({"type": "error", "msg_id": msg_id, "code": "invalid_data"}))
            return

        resp = await self._enqueue_to_cloud(sess.client_id, msg_id, event, data)
        self._messages_to_cloud += 1
        async with sess.send_lock:
            await sess.ws.send(json.dumps(resp))

