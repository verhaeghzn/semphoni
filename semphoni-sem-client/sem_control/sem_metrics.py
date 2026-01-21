"""
SEM metrics abstraction.

This repo primarily automates the SEM GUI, but for certain SEMs we can read
telemetry via a local vendor SDK / remote-control interface.

Env:
- SEM_MODE:
    - "gui" (default): no vendor telemetry available
    - "tescan_mira3": connect to TESCAN SharkSEM (TCP) and expose get_metrics
"""

from __future__ import annotations

import logging
import os
import time
from dataclasses import dataclass
from typing import Any, Dict, Optional

from .tescan_sharksem import SharkSemClient, SharkSemError

logger = logging.getLogger(__name__)


def _env_bool(name: str, default: bool = False) -> bool:
    v = (os.getenv(name) or "").strip().lower()
    if not v:
        return default
    return v in {"1", "true", "yes", "y", "on"}


def get_sem_mode() -> str:
    return (os.getenv("SEM_MODE") or "gui").strip().lower()


def _kv_from_voltage(v: float) -> float:
    # Some installs report volts, some report kV. Heuristic:
    # - if value is > 1000, assume volts
    # - else assume kV
    if v > 1000.0:
        return v / 1000.0
    return v


def _pump_status_label(code: int) -> str:
    # Codes vary by Tescan Control version; keep a conservative mapping and
    # always include the raw code in responses.
    return {
        0: "unknown",
        1: "ready",
        2: "pumping",
        3: "venting",
        4: "error",
    }.get(int(code), f"unknown({int(code)})")


@dataclass
class TescanMira3MetricsReader:
    host: str
    port: int
    timeout_s: float = 2.0
    reconnect_backoff_s: float = 1.0

    _client: Optional[SharkSemClient] = None
    _last_connect_attempt_s: float = 0.0

    def _ensure_connected(self) -> SharkSemClient:
        now = time.time()
        if self._client is not None and self._client.is_connected():
            return self._client

        # Avoid tight reconnect loops if the SEM is offline.
        if now - self._last_connect_attempt_s < self.reconnect_backoff_s:
            raise SharkSemError("TESCAN SDK not connected (backoff)")

        self._last_connect_attempt_s = now
        c = SharkSemClient(host=self.host, port=self.port, timeout_s=self.timeout_s)
        c.connect()
        self._client = c
        return c

    def get_metrics(self) -> Dict[str, Any]:
        c = self._ensure_connected()

        # High voltage + emission current
        hv_v = float(c.recv_float("HVGetVoltage"))
        hv_kv = float(_kv_from_voltage(hv_v))
        emission_a = float(c.recv_float("HVGetEmission"))

        # Beam state
        beam_state = int(c.recv_int("HVGetBeam"))
        beam_on = bool(beam_state != 0)

        # Stage position: x, y, z, rot, tilt
        x, y, z, r, t = c.recv("StgGetPosition", ["float", "float", "float", "float", "float"])

        # Working distance
        wd = float(c.recv_float("GetWD"))

        # Vacuum / pump status
        vac_code = int(c.recv_int("VacGetStatus"))

        return {
            "sem_mode": "tescan_mira3",
            "source": {"transport": "sharksem", "host": self.host, "port": self.port},
            "beam_kv": hv_kv,
            "beam_current_a": emission_a,
            "beam_on": beam_on,
            "stage": {"x": float(x), "y": float(y), "z": float(z), "r": float(r), "t": float(t)},
            "working_distance": {"wd": wd, "z": float(z)},
            "pump": {"status": _pump_status_label(vac_code), "status_code": vac_code},
        }


_TES_CAN: Optional[TescanMira3MetricsReader] = None


def get_metrics() -> Dict[str, Any]:
    """
    Return a JSON-serializable dict of key SEM metrics.

    In SEM_MODE=gui this returns a deterministic "not supported" payload so the
    caller can surface a clear message.
    """
    mode = get_sem_mode()
    if mode != "tescan_mira3":
        return {
            "sem_mode": mode,
            "supported": False,
            "message": "SEM metrics not available in this mode (set SEM_MODE=tescan_mira3).",
        }

    host = (os.getenv("TESCAN_SDK_HOST") or "127.0.0.1").strip()
    port = int(os.getenv("TESCAN_SDK_PORT") or "8300")
    timeout_s = float(os.getenv("TESCAN_SDK_TIMEOUT_S") or "2.0")

    global _TES_CAN
    if _TES_CAN is None or _TES_CAN.host != host or _TES_CAN.port != port or _TES_CAN.timeout_s != timeout_s:
        _TES_CAN = TescanMira3MetricsReader(host=host, port=port, timeout_s=timeout_s)

    try:
        metrics = _TES_CAN.get_metrics()
        metrics["supported"] = True
        return metrics
    except Exception as e:
        # Don't throw from metrics: the caller (WS command or REST) should still respond.
        return {
            "sem_mode": mode,
            "supported": False,
            "message": f"Failed to read metrics from TESCAN SDK: {e}",
        }


def check_sem_mode_at_startup() -> None:
    """
    Check if SEM mode works by calling get_metrics once during startup.
    If it doesn't work, log a warning and fall back to "gui" (NO SEM) mode.
    """
    mode = get_sem_mode()
    if mode != "tescan_mira3":
        # Not in tescan_mira3 mode, nothing to check
        return

    logger.info("Checking SEM mode connectivity...")
    try:
        metrics = get_metrics()
        if metrics.get("supported", False):
            logger.info(
                "SEM mode check passed: successfully connected to TESCAN SDK "
                f"(host={metrics.get('source', {}).get('host', 'unknown')}, "
                f"port={metrics.get('source', {}).get('port', 'unknown')})"
            )
        else:
            # Metrics not supported, fall back to gui mode
            error_msg = metrics.get("message", "Unknown error")
            logger.warning(
                f"SEM mode check failed: {error_msg}. "
                "Falling back to default (NO SEM) mode."
            )
            os.environ["SEM_MODE"] = "gui"
    except Exception as e:
        # Unexpected error during check
        logger.warning(
            f"SEM mode check failed with exception: {e}. "
            "Falling back to default (NO SEM) mode."
        )
        os.environ["SEM_MODE"] = "gui"

