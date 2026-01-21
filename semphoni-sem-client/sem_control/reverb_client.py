"""
Reverb (Pusher-compatible) WebSocket client for SEMPhoni control.

Protocol summary:
- Connect to a Pusher-compatible WS endpoint.
- Wait for "pusher:connection_established", parse socket_id.
- Call auth endpoint (HTTP POST) with X-Client-Key to get auth + channel_data.
- Subscribe to presence channel.
- Send "client-heartbeat" every 10 seconds.
- Listen for "server-command" and respond with "client-command-result".

Credentials are intended to be provided through environment variables.
"""

from __future__ import annotations

import asyncio
import base64
import contextlib
import io
import json
import logging
import os
import random
import ssl
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from typing import Any, Awaitable, Callable, Dict, List, Optional, Tuple
from urllib.parse import urlparse, urlunparse

import mss
import pyautogui
from PIL import Image

from .utils import ButtonValidationError, validate_button
from .sem_metrics import check_sem_mode_at_startup, get_metrics as get_sem_metrics

logger = logging.getLogger(__name__)

DEFAULT_MAX_MESSAGE_BYTES = 1 * 1024 * 1024


def _json_log(event: str, **fields: Any) -> None:
    payload = {"event": event, **fields}
    try:
        logger.info("%s", json.dumps(payload, separators=(",", ":"), sort_keys=True))
    except Exception:
        logger.info("event=%s fields=%r", event, fields)


DEFAULT_WS_HOST = "ws.semphoni.multiscale.nl"
DEFAULT_APP_ID = "626126"
DEFAULT_APP_KEY = "mtnrfqng7jaloh9uq1gi"
# Default query matches the original python client behavior.
# If needed, override via REVERB_WS_PROTOCOL_QUERY or REVERB_WS_URL.
DEFAULT_PROTOCOL_QUERY = "protocol=7&client=python&version=0.1&flash=false"


@dataclass(frozen=True)
class ReverbClientConfig:
    ws_url: str
    auth_url: str
    channel: str
    client_key: str
    ws_origin: str = ""
    ws_user_agent: str = ""
    app_id: str = DEFAULT_APP_ID
    heartbeat_seconds: int = 10
    reconnect_delay_seconds: int = 60
    version: str = "dev"
    insecure_ssl: bool = False
    log_heartbeats: bool = False
    max_message_bytes: int = DEFAULT_MAX_MESSAGE_BYTES
    relay_outbox_max_total: int = 1000
    relay_outbox_max_per_client: int = 100

    @staticmethod
    def from_env() -> "ReverbClientConfig":
        """
        Environment variables:
        - REVERB_WS_URL (optional): full WebSocket URL
        - REVERB_WS_HOST (optional): host or base WebSocket URL (default ws.semphoni.multiscale.nl)
        - REVERB_APP_ID (optional): informational app id (default 915841)
        - REVERB_APP_KEY (optional): if set, build /app/<key>?<protocol query> from REVERB_WS_HOST
        - REVERB_AUTH_URL (required): HTTP auth endpoint
        - REVERB_CLIENT_KEY (required): value for X-Client-Key header
        - REVERB_CHANNEL (optional): default presence-client.1
        - REVERB_HEARTBEAT_SECONDS (optional): default 10
        - REVERB_RECONNECT_DELAY_SECONDS (optional): default 60
        - REVERB_VERSION (optional): default dev
        - REVERB_INSECURE_SSL (optional): set to "1" to skip TLS verification (useful for self-signed)
        - REVERB_LOG_HEARTBEATS (optional): set to "1" to log each heartbeat at INFO
        - REVERB_MAX_MESSAGE_BYTES (optional): max WS message size (default 1 MiB)
        - RELAY_OUTBOX_MAX_TOTAL (optional): max total queued local->cloud messages (default 1000)
        - RELAY_OUTBOX_MAX_PER_CLIENT (optional): max queued local->cloud messages per client (default 100)
        """
        ws_url = os.getenv("REVERB_WS_URL")
        if not ws_url:
            host = os.getenv("REVERB_WS_HOST", DEFAULT_WS_HOST).strip()
            if host.startswith("wss://") or host.startswith("ws://"):
                base = host
            else:
                # Heuristic: DDEV commonly uses :8080 for HTTP and :8443 for HTTPS.
                # If the user includes an explicit :8080 without a scheme, default to `ws://`.
                scheme = "ws" if host.endswith(":8080") else "wss"
                base = f"{scheme}://{host}"

            # If base already includes /app/, keep it (assume it is a full WS URL).
            if "/app/" in base:
                ws_url = base
            else:
                # Default to the known app key so the default host works out of the box.
                app_key = (os.getenv("REVERB_APP_KEY") or DEFAULT_APP_KEY).strip()
                protocol_query = (os.getenv("REVERB_WS_PROTOCOL_QUERY") or DEFAULT_PROTOCOL_QUERY).strip()
                ws_url = f"{base.rstrip('/')}/app/{app_key}?{protocol_query}"

        app_id = (os.getenv("REVERB_APP_ID") or DEFAULT_APP_ID).strip()

        auth_url = (os.getenv("REVERB_AUTH_URL") or "").strip()
        if not auth_url:
            raise ValueError("Missing REVERB_AUTH_URL (HTTP auth endpoint).")

        client_key = (os.getenv("REVERB_CLIENT_KEY") or "").strip()
        if not client_key:
            raise ValueError("Missing REVERB_CLIENT_KEY (X-Client-Key header value).")

        channel = os.getenv("REVERB_CHANNEL", "presence-client.1").strip()

        heartbeat_seconds = int(os.getenv("REVERB_HEARTBEAT_SECONDS", "10"))
        reconnect_delay_seconds = int(os.getenv("REVERB_RECONNECT_DELAY_SECONDS", "60"))
        version = os.getenv("REVERB_VERSION", "dev").strip()
        insecure_ssl = (os.getenv("REVERB_INSECURE_SSL", "").strip() in {"1", "true", "TRUE", "yes", "YES"})
        log_heartbeats = (os.getenv("REVERB_LOG_HEARTBEATS", "").strip() in {"1", "true", "TRUE", "yes", "YES"})
        max_message_bytes = int(os.getenv("REVERB_MAX_MESSAGE_BYTES", str(DEFAULT_MAX_MESSAGE_BYTES)))
        relay_outbox_max_total = int(os.getenv("RELAY_OUTBOX_MAX_TOTAL", "1000"))
        relay_outbox_max_per_client = int(os.getenv("RELAY_OUTBOX_MAX_PER_CLIENT", "100"))

        # Some Pusher/Reverb frontends (and some edge/WAF setups) require an Origin header
        # matching the browser UI host. Allow forcing it; otherwise infer for semphoni.
        ws_origin = (os.getenv("REVERB_WS_ORIGIN") or "").strip()
        if not ws_origin:
            try:
                p_ws = urlparse(ws_url)
                if p_ws.hostname and p_ws.hostname.endswith("semphoni.multiscale.nl"):
                    ws_origin = "https://semphoni.multiscale.nl"
            except Exception:
                ws_origin = ""

        ws_user_agent = (os.getenv("REVERB_WS_USER_AGENT") or "").strip()

        return ReverbClientConfig(
            ws_url=ws_url,
            auth_url=auth_url,
            channel=channel,
            client_key=client_key,
            ws_origin=ws_origin,
            ws_user_agent=ws_user_agent,
            app_id=app_id,
            heartbeat_seconds=heartbeat_seconds,
            reconnect_delay_seconds=reconnect_delay_seconds,
            version=version,
            insecure_ssl=insecure_ssl,
            log_heartbeats=log_heartbeats,
            max_message_bytes=max_message_bytes,
            relay_outbox_max_total=relay_outbox_max_total,
            relay_outbox_max_per_client=relay_outbox_max_per_client,
        )


def _http_post_json(
    url: str,
    headers: Dict[str, str],
    payload: Dict[str, Any],
    *,
    insecure_ssl: bool = False,
) -> Dict[str, Any]:
    body = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json", **headers},
        method="POST",
    )
    try:
        ctx = None
        if insecure_ssl and urlparse(url).scheme == "https":
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE

        with urllib.request.urlopen(req, timeout=30, context=ctx) as resp:
            resp_body = resp.read().decode("utf-8", errors="replace")
            return json.loads(resp_body) if resp_body else {}
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", errors="replace") if e.fp else ""
        raise RuntimeError(f"Auth HTTP error {e.code}: {raw}") from e


def _is_ddev_hostname(hostname: Optional[str]) -> bool:
    return bool(hostname) and hostname.endswith(".ddev.site")


def _add_or_replace_port(url: str, *, scheme: Optional[str], port: int) -> str:
    """
    Return a URL identical to `url` but with the given scheme/port.
    Preserves path/query/fragment.
    """
    p = urlparse(url)
    if not p.hostname:
        return url

    new_scheme = scheme or p.scheme
    netloc = p.hostname
    if p.username or p.password:
        auth = p.username or ""
        if p.password:
            auth = f"{auth}:{p.password}"
        netloc = f"{auth}@{netloc}"
    netloc = f"{netloc}:{port}"

    return urlunparse((new_scheme, netloc, p.path, p.params, p.query, p.fragment))


def _ddev_ws_url_candidates(ws_url: str) -> List[str]:
    """
    For DDEV domains without explicit port, try common router ports.
    - HTTP websocket: ws://<host>:8080/...
    - HTTPS websocket: wss://<host>:8443/...
    """
    p = urlparse(ws_url)
    if not _is_ddev_hostname(p.hostname) or p.port is not None:
        return [ws_url]

    candidates = [
        ws_url,
        _add_or_replace_port(ws_url, scheme="ws", port=8080),
        _add_or_replace_port(ws_url, scheme="wss", port=8443),
    ]
    out: List[str] = []
    seen = set()
    for u in candidates:
        if u not in seen:
            seen.add(u)
            out.append(u)
    return out


def _ddev_auth_url_candidates(auth_url: str) -> List[str]:
    """
    For DDEV domains without explicit port, try common router ports.
    - http://<host>:8080/...
    - https://<host>:8443/...
    """
    p = urlparse(auth_url)
    if not _is_ddev_hostname(p.hostname) or p.port is not None:
        return [auth_url]

    candidates = [
        auth_url,
        _add_or_replace_port(auth_url, scheme="http", port=8080),
        _add_or_replace_port(auth_url, scheme="https", port=8443),
    ]
    out: List[str] = []
    seen = set()
    for u in candidates:
        if u not in seen:
            seen.add(u)
            out.append(u)
    return out


def _parse_pusher_data_field(data: Any) -> Any:
    # Pusher often encodes `data` as a JSON string.
    if isinstance(data, str):
        try:
            return json.loads(data)
        except Exception:
            return data
    return data


def _execute_command(
    command_name: str, payload: Dict[str, Any]
) -> Tuple[bool, str, Optional[Dict[str, Any]]]:
    """
    Minimal command execution layer (no Flask).
    Expand this mapping as the server begins sending more commands.
    """
    try:
        if command_name in ("get_metrics", "getMetrics", "get-metrics"):
            metrics = get_sem_metrics()
            if not metrics.get("supported", False):
                return False, str(metrics.get("message") or "metrics_not_supported"), metrics
            return True, "ok", metrics

        if command_name in ("screenshot", "getScreenshot", "get_screenshot"):
            # Match admin_control.py behavior (mss + SEMPC_MONITOR_NUMBER).
            monitor_nr = int(payload.get("monitor_nr") or os.getenv("SEMPC_MONITOR_NUMBER", 2))
            t0 = time.time()
            # Optional knobs:
            # - payload.quality / SCREENSHOT_JPEG_QUALITY (1-100)
            # - payload.format must be jpeg if provided (for forward-compat)
            fmt = str(payload.get("format") or "jpeg").strip().lower()
            if fmt not in {"jpeg", "jpg", "image/jpeg"}:
                return False, "Only JPEG screenshots are supported", None

            quality = int(payload.get("quality") or os.getenv("SCREENSHOT_JPEG_QUALITY", "75"))
            quality = max(1, min(100, quality))

            _json_log(
                "screenshot_start",
                monitor_nr=monitor_nr,
                format="jpeg",
                quality=quality,
            )

            with mss.mss() as sct:
                monitor = sct.monitors[monitor_nr]
                t_grab0 = time.time()
                sct_img = sct.grab(monitor)
                t_grab1 = time.time()
                # Convert raw RGB buffer into a PIL image and encode as JPEG.
                img = Image.frombytes("RGB", sct_img.size, sct_img.rgb)
                buf = io.BytesIO()
                t_enc0 = time.time()
                img.save(buf, format="JPEG", quality=quality, optimize=True)
                t_enc1 = time.time()
                img_bytes = buf.getvalue()
                mime = "image/jpeg"

            b64 = base64.b64encode(img_bytes).decode("ascii")
            t1 = time.time()
            _json_log(
                "screenshot_done",
                monitor_nr=monitor_nr,
                format="jpeg",
                quality=quality,
                bytes=len(img_bytes),
                width=int(sct_img.size.width),
                height=int(sct_img.size.height),
                grab_ms=round((t_grab1 - t_grab0) * 1000.0, 2),
                encode_ms=round((t_enc1 - t_enc0) * 1000.0, 2),
                total_ms=round((t1 - t0) * 1000.0, 2),
            )
            return True, "ok", {
                "mime": mime,
                "encoding": "base64",
                "jpeg_base64": b64,
                "format": "jpeg",
                "quality": quality,
                "bytes": len(img_bytes),
                "monitor_nr": monitor_nr,
                "size": {"width": int(sct_img.size.width), "height": int(sct_img.size.height)},
            }

        if command_name in ("gotoButton", "goto_button"):
            button_name = str(payload["button_name"])
            duration = float(payload.get("duration", 0.3))
            _, center = validate_button(button_name)
            pyautogui.moveTo(center["x"], center["y"], duration=duration)
            return True, "ok", None

        if command_name in ("clickButton", "click_button"):
            button_name = str(payload["button_name"])
            duration = float(payload.get("duration", 0.3))
            clicks = int(payload.get("clicks", 1))
            interval = float(payload.get("interval", 0.1))
            button = str(payload.get("button", "left"))
            confirmation_wait = float(payload.get("confirmation_wait", 1.0))

            button_info, center = validate_button(button_name)
            pyautogui.moveTo(center["x"], center["y"], duration=duration)
            pyautogui.click(center["x"], center["y"], clicks=clicks, interval=interval, button=button)

            if button_info.get("requires_confirmation", False):
                time.sleep(confirmation_wait)
                pyautogui.press("enter")

            return True, "ok", None

        if command_name in ("move-click", "move_click"):
            x = int(payload["x"])
            y = int(payload["y"])
            duration = float(payload.get("duration", 0.2))
            pyautogui.moveTo(x, y, duration=duration)
            pyautogui.click()
            return True, "ok", None

        if command_name in ("type-text", "type_text"):
            text = str(payload["text"])
            interval = float(payload.get("interval", 0.02))
            pyautogui.typewrite(text, interval=interval)
            return True, "ok", None

        if command_name in ("key-press", "key_press"):
            key = str(payload["key"])
            pyautogui.press(key)
            return True, "ok", None

        return False, f"Unknown command_name: {command_name}", None
    except ButtonValidationError as e:
        return False, e.message, None
    except Exception as e:
        return False, str(e), None


async def _send_json(ws, obj: Dict[str, Any]) -> None:
    # Reverb/Pusher wire format commonly uses `data` as a JSON string.
    # Incoming messages may encode `data` as a string; we parse it via `_parse_pusher_data_field`.
    # For maximum compatibility (and to avoid "silent drops" on large payloads), encode
    # outgoing `data` when it is a dict/list.
    out = dict(obj)
    data = out.get("data")
    if data is not None and not isinstance(data, str):
        out["data"] = json.dumps(data, separators=(",", ":"))
    await ws.send(json.dumps(out, separators=(",", ":")))


async def _await_connection_established(ws) -> str:
    while True:
        raw = await ws.recv()
        msg = json.loads(raw)
        if msg.get("event") == "pusher:connection_established":
            data = _parse_pusher_data_field(msg.get("data"))
            if isinstance(data, dict) and data.get("socket_id"):
                return str(data["socket_id"])
            raise RuntimeError(f"Unexpected connection_established payload: {data!r}")


async def _subscribe(ws, cfg: ReverbClientConfig, socket_id: str) -> None:
    last_err: Optional[Exception] = None
    for auth_url in _ddev_auth_url_candidates(cfg.auth_url):
        try:
            if auth_url != cfg.auth_url:
                logger.info("Retrying auth via %s", auth_url)
            auth_resp = _http_post_json(
                auth_url,
                headers={"X-Client-Key": cfg.client_key},
                payload={"socket_id": socket_id, "channel_name": cfg.channel},
                insecure_ssl=cfg.insecure_ssl,
            )
            auth = auth_resp.get("auth")
            channel_data = auth_resp.get("channel_data")
            if not auth:
                raise RuntimeError(f"Auth response missing 'auth': {auth_resp}")
            if not channel_data:
                raise RuntimeError(f"Auth response missing 'channel_data': {auth_resp}")

            await _send_json(
                ws,
                {
                    "event": "pusher:subscribe",
                    "data": {
                        "channel": cfg.channel,
                        "auth": auth,
                        "channel_data": channel_data,
                    },
                },
            )
            return
        except Exception as e:
            last_err = e
            continue

    raise RuntimeError(f"Failed to authenticate/subscribe using {cfg.auth_url}") from last_err


async def _heartbeat_loop(ws, cfg: ReverbClientConfig) -> None:
    while True:
        await asyncio.sleep(cfg.heartbeat_seconds)
        ts = int(time.time())
        await _send_json(
            ws,
            {
                "event": "client-heartbeat",
                "channel": cfg.channel,
                "data": {"ts": ts, "version": cfg.version},
            },
        )
        if cfg.log_heartbeats:
            logger.info("Sent heartbeat ts=%s channel=%s", ts, cfg.channel)


def _inject_relay(data: Dict[str, Any], *, client_id: str, msg_id: str) -> Dict[str, Any]:
    out = dict(data)
    out["relay"] = {"client_id": client_id, "msg_id": msg_id}
    return out


def _extract_and_strip_relay(data: Dict[str, Any]) -> Tuple[Optional[str], str, Dict[str, Any]]:
    relay = data.get("relay")
    if not isinstance(relay, dict):
        return None, "", data
    client_id = str(relay.get("client_id", "")).strip() or None
    msg_id = str(relay.get("msg_id", "")).strip()
    stripped = dict(data)
    stripped.pop("relay", None)
    return client_id, msg_id, stripped


async def _cloud_message_loop(ws, cfg: ReverbClientConfig, relay_gateway: Optional[Any]) -> None:
    async for raw in ws:
        try:
            msg = json.loads(raw)
        except Exception:
            logger.warning("Non-JSON WS message: %r", raw)
            continue

        event = msg.get("event")

        # Keep-alive (Pusher protocol)
        if event == "pusher:ping":
            await _send_json(ws, {"event": "pusher:pong", "data": {}})
            continue

        if event == "pusher:error":
            logger.error("pusher:error: %s", msg.get("data"))
            continue

        if event != "server-command":
            # Useful during early integration to see what's coming in.
            logger.debug("WS event: %s", event)
            continue

        data = _parse_pusher_data_field(msg.get("data"))
        if not isinstance(data, dict):
            logger.warning("server-command with unexpected data: %r", data)
            continue

        logger.info("Received server-command")

        relay_client_id, relay_msg_id, stripped = _extract_and_strip_relay(data)
        if relay_gateway is not None and relay_client_id:
            routed = await relay_gateway.send_from_cloud(
                relay_client_id,
                msg_id=relay_msg_id,
                event="server-command",
                data=stripped,
            )
            if not routed:
                _json_log(
                    "cloud_to_local_route_failed",
                    client_id=relay_client_id,
                    relay_msg_id=relay_msg_id,
                )
                # Don't fail silently: report back to cloud so the UI can surface it.
                correlation_id = str(stripped.get("correlation_id", ""))
                command_name = str(stripped.get("command_name", ""))
                await _send_json(
                    ws,
                    {
                        "event": "client-command-result",
                        "channel": cfg.channel,
                        "data": {
                            "correlation_id": correlation_id,
                            "command_name": command_name,
                            "ok": False,
                            "message": f"Relay client not connected: {relay_client_id}",
                        },
                    },
                )
            continue

        correlation_id = str(stripped.get("correlation_id", ""))
        command_name = str(stripped.get("command_name", ""))
        payload = stripped.get("payload") or {}
        if not isinstance(payload, dict):
            payload = {}

        ok, message, result_payload = _execute_command(command_name, payload)
        result_data: Dict[str, Any] = {
            "correlation_id": correlation_id,
            "command_name": command_name,
            "ok": bool(ok),
            "message": message,
        }
        if result_payload is not None:
            result_data["payload"] = result_payload
        await _send_json(
            ws,
            {
                "event": "client-command-result",
                "channel": cfg.channel,
                "data": {
                    **result_data,
                },
            },
        )
        logger.info("Sent client-command-result ok=%s command=%s", bool(ok), command_name)

    # If we reach here, the websocket iterator ended, meaning the connection closed.
    # Treat this as a disconnect so the outer retry loop applies backoff instead of
    # reconnecting in a tight loop (e.g. during Reverb restarts).
    raise RuntimeError("Cloud WebSocket closed")


@dataclass(frozen=True)
class CloudOutboxItem:
    client_id: str
    msg_id: str
    frame: Dict[str, Any]


class CloudOutbox:
    def __init__(self, *, max_total: int, max_per_client: int) -> None:
        self.queue: asyncio.Queue[CloudOutboxItem] = asyncio.Queue(maxsize=max_total)
        self._max_per_client = max_per_client
        self._pending_by_client: Dict[str, int] = {}
        self._lock = asyncio.Lock()

    async def try_put(self, item: CloudOutboxItem) -> Tuple[bool, str]:
        async with self._lock:
            if self.queue.full():
                return False, "queue_full"
            pending = self._pending_by_client.get(item.client_id, 0)
            if pending >= self._max_per_client:
                return False, "client_queue_full"
            self.queue.put_nowait(item)
            self._pending_by_client[item.client_id] = pending + 1
            return True, "ok"

    async def mark_done(self, client_id: str) -> None:
        async with self._lock:
            pending = self._pending_by_client.get(client_id, 0)
            if pending <= 1:
                self._pending_by_client.pop(client_id, None)
            else:
                self._pending_by_client[client_id] = pending - 1

    async def drop(self, client_id: str) -> None:
        # Alias for mark_done, but semantically used when we discard a queued item.
        await self.mark_done(client_id)

    async def requeue_existing(self, item: CloudOutboxItem) -> bool:
        # Re-queue without touching per-client counters (already accounted for).
        try:
            self.queue.put_nowait(item)
            return True
        except Exception:
            return False


async def _cloud_sender_loop(ws: Any, outbox: CloudOutbox) -> None:
    while True:
        item = await outbox.queue.get()
        try:
            await _send_json(ws, item.frame)
            await outbox.mark_done(item.client_id)
        except Exception as e:
            # Best-effort requeue of the in-flight item (so reconnect can deliver it).
            requeued = await outbox.requeue_existing(item)
            if not requeued:
                await outbox.drop(item.client_id)
                _json_log(
                    "cloud_outbox_drop_inflight",
                    client_id=item.client_id,
                    msg_id=item.msg_id,
                    error=str(e),
                )
            raise
        finally:
            outbox.queue.task_done()


async def _connect_and_run_cloud_session(
    cfg: ReverbClientConfig,
    *,
    relay_gateway: Optional[Any],
    outbox: CloudOutbox,
    cloud_connected: asyncio.Event,
) -> None:
    # Import here so "pip install websockets" is only required for WS mode.
    import websockets  # type: ignore

    last_err: Optional[Exception] = None
    for ws_url in _ddev_ws_url_candidates(cfg.ws_url):
        try:
            if ws_url != cfg.ws_url:
                _json_log("cloud_ws_retry_url", ws_url=ws_url)
            else:
                _json_log("cloud_ws_connecting", ws_url=ws_url)

            p = urlparse(ws_url)
            ssl_ctx = None
            if cfg.insecure_ssl and p.scheme == "wss":
                ssl_ctx = ssl.create_default_context()
                ssl_ctx.check_hostname = False
                ssl_ctx.verify_mode = ssl.CERT_NONE

            headers: List[Tuple[str, str]] = []
            if cfg.ws_origin:
                headers.append(("Origin", cfg.ws_origin))
            if cfg.ws_user_agent:
                headers.append(("User-Agent", cfg.ws_user_agent))

            # Only set 'ssl' key if needed, to avoid incompatibility with wss:// and ssl=None.
            connect_kwargs: Dict[str, Any] = dict(
                ping_interval=None,
                max_size=cfg.max_message_bytes,
            )
            if ssl_ctx is not None:
                connect_kwargs["ssl"] = ssl_ctx

            # websockets has changed kwarg naming over time:
            # - older: extra_headers
            # - newer: additional_headers
            try:
                connect_cm = websockets.connect(ws_url, additional_headers=headers, **connect_kwargs)
            except TypeError:
                connect_cm = websockets.connect(ws_url, extra_headers=headers, **connect_kwargs)

            async with connect_cm as ws:
                socket_id = await _await_connection_established(ws)
                cloud_connected.set()
                _json_log("cloud_ws_connected", socket_id=socket_id)

                await _subscribe(ws, cfg, socket_id)
                _json_log("cloud_ws_subscribed", channel=cfg.channel)

                hb_task = asyncio.create_task(_heartbeat_loop(ws, cfg))
                sender_task = asyncio.create_task(_cloud_sender_loop(ws, outbox))
                try:
                    await _cloud_message_loop(ws, cfg, relay_gateway)
                finally:
                    cloud_connected.clear()
                    hb_task.cancel()
                    sender_task.cancel()
                    with contextlib.suppress(Exception):
                        await hb_task
                    with contextlib.suppress(Exception):
                        await sender_task
            return
        except Exception as e:
            last_err = e
            _json_log(
                "cloud_ws_connect_failed",
                ws_url=ws_url,
                error_type=type(e).__name__,
                error=str(e),
                error_repr=repr(e),
            )
            continue

    raise RuntimeError(f"Failed to connect to WS using {cfg.ws_url}") from last_err


async def _cloud_connect_forever(
    cfg: ReverbClientConfig,
    *,
    relay_gateway: Optional[Any],
    outbox: CloudOutbox,
    cloud_connected: asyncio.Event,
) -> None:
    delay = 1.0
    cap = float(max(1, cfg.reconnect_delay_seconds))
    while True:
        try:
            await _connect_and_run_cloud_session(
                cfg,
                relay_gateway=relay_gateway,
                outbox=outbox,
                cloud_connected=cloud_connected,
            )
            delay = 1.0
        except Exception as e:
            cloud_connected.clear()
            sleep_s = min(cap, delay)
            jitter = random.uniform(0.0, sleep_s * 0.2)
            _json_log("cloud_ws_disconnected", error=str(e), retry_in_seconds=round(sleep_s + jitter, 3))
            await asyncio.sleep(sleep_s + jitter)
            delay = min(cap, delay * 2.0)


async def _run_reverb_client_with_local_relay(cfg: ReverbClientConfig) -> None:
    cloud_connected = asyncio.Event()
    outbox = CloudOutbox(max_total=cfg.relay_outbox_max_total, max_per_client=cfg.relay_outbox_max_per_client)

    relay_gateway: Optional[Any] = None
    try:
        from .relay_gateway import RelayConfig, RelayGateway  # local module

        relay_cfg = RelayConfig.from_env()

        async def enqueue_to_cloud(client_id: str, msg_id: str, event: str, data: Dict[str, Any]) -> Dict[str, Any]:
            frame = {
                "event": event,
                "channel": cfg.channel,
                "data": _inject_relay(data, client_id=client_id, msg_id=msg_id),
            }
            ok, reason = await outbox.try_put(CloudOutboxItem(client_id=client_id, msg_id=msg_id, frame=frame))
            if not ok:
                return {
                    "type": "error",
                    "msg_id": msg_id,
                    "code": reason,
                    "message": "Unable to enqueue message for cloud",
                    "cloud_connected": bool(cloud_connected.is_set()),
                }
            return {
                "type": "ack",
                "msg_id": msg_id,
                "status": "queued",
                "cloud_connected": bool(cloud_connected.is_set()),
            }

        if not relay_cfg.token:
            # If there's no token, local clients can never connect; don't start the relay.
            # This avoids "black-holing" cloud commands that contain a relay envelope.
            _json_log("local_relay_disabled", reason="LOCAL_RELAY_TOKEN is empty")
            relay_gateway = None
        else:
            relay_gateway = RelayGateway(
                relay_cfg,
                enqueue_to_cloud=enqueue_to_cloud,
                cloud_connected=lambda: bool(cloud_connected.is_set()),
            )
            await relay_gateway.start()
    except Exception as e:
        relay_gateway = None
        _json_log("local_relay_start_failed", error=str(e))

    await _cloud_connect_forever(cfg, relay_gateway=relay_gateway, outbox=outbox, cloud_connected=cloud_connected)


def run_reverb_client_forever() -> None:
    """
    Blocking entrypoint that retries forever.

    This now also starts a local LAN WebSocket relay server on PC2 so that
    additional LAN-only clients (PC1, etc.) can connect to PC2 and have their
    messages forwarded over the single existing cloud WebSocket connection.
    """
    # Ensure logging is configured even if called standalone.
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s - %(levelname)s - %(message)s",
    )
    # If something unexpected bubbles up (e.g. connection reset during Reverb restarts,
    # dependency/runtime issues, etc.), do not exit the process. Restart after a delay.
    base_delay_s = float(os.getenv("REVERB_MAIN_RESTART_DELAY_SECONDS", "2.0"))
    cap_delay_s = float(os.getenv("REVERB_MAIN_RESTART_DELAY_CAP_SECONDS", "60.0"))
    delay_s = max(0.1, base_delay_s)

    while True:
        try:
            # Check SEM mode connectivity at startup
            check_sem_mode_at_startup()
            cfg = ReverbClientConfig.from_env()
            asyncio.run(_run_reverb_client_with_local_relay(cfg))
            # If the asyncio run returns normally, reset delay and continue (should be rare;
            # the inner WS loop is intended to run forever).
            delay_s = max(0.1, base_delay_s)
        except KeyboardInterrupt:
            raise
        except asyncio.CancelledError as e:
            # In some Python versions this inherits BaseException; keep the process alive.
            _json_log(
                "reverb_main_cancelled",
                error_type=type(e).__name__,
                error=str(e),
                retry_in_seconds=round(delay_s, 3),
            )
            time.sleep(delay_s)
            delay_s = min(cap_delay_s, delay_s * 2.0)
        except Exception as e:
            _json_log(
                "reverb_main_crashed",
                error_type=type(e).__name__,
                error=str(e),
                error_repr=repr(e),
                retry_in_seconds=round(delay_s, 3),
            )
            time.sleep(delay_s)
            delay_s = min(cap_delay_s, delay_s * 2.0)

