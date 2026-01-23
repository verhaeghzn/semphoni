"""
TESCAN SEM metrics reader via SharkSEM SDK.
"""

from __future__ import annotations

import logging
import os
import time
from dataclasses import dataclass
from typing import Any, Dict, Optional

from .sdk_client import SharkSemClient, SharkSemError

logger = logging.getLogger(__name__)


def _kv_from_voltage(v: float) -> float:
    """Convert voltage to kV. Some installs report volts, some report kV."""
    if v > 1000.0:
        return v / 1000.0
    return v


def _pump_status_label(code: int) -> str:
    """Convert pump status code to human-readable label."""
    return {
        0: "unknown",
        1: "ready",
        2: "pumping",
        3: "venting",
        4: "error",
    }.get(int(code), f"unknown({int(code)})")


@dataclass
class TescanMira3MetricsReader:
    """Reader for TESCAN MIRA 3 metrics via SharkSEM SDK."""

    host: str
    port: int
    timeout_s: float = 2.0
    reconnect_backoff_s: float = 1.0

    _client: Optional[SharkSemClient] = None
    _last_connect_attempt_s: float = 0.0

    def _ensure_connected(self) -> SharkSemClient:
        """Ensure SDK client is connected, reconnecting if necessary."""
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
        """Retrieve metrics from TESCAN SDK."""
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


class TescanMetricsReader:
    """Wrapper for TESCAN metrics reader with environment-based configuration."""

    def __init__(self):
        """Initialize metrics reader with environment configuration."""
        host = (os.getenv("TESCAN_SDK_HOST") or "127.0.0.1").strip()
        port = int(os.getenv("TESCAN_SDK_PORT") or "8300")
        timeout_s = float(os.getenv("TESCAN_SDK_TIMEOUT_S") or "2.0")
        self.reader = TescanMira3MetricsReader(host=host, port=port, timeout_s=timeout_s)

    def check_connectivity(self) -> bool:
        """
        Check if SDK connectivity works.

        Returns:
            True if connected successfully, False otherwise
        """
        try:
            self.reader.get_metrics()
            return True
        except Exception as e:
            logger.warning("TESCAN SDK connectivity check failed: %s", e)
            return False

    def get_metrics(self) -> Dict[str, Any]:
        """
        Get metrics from TESCAN SDK.

        Returns:
            Dict with metrics or error information
        """
        try:
            metrics = self.reader.get_metrics()
            metrics["supported"] = True
            return metrics
        except Exception as e:
            return {
                "sem_mode": "tescan_mira3",
                "supported": False,
                "message": f"Failed to read metrics from TESCAN SDK: {e}",
            }
