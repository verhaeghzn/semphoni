"""
Command execution and routing.

Routes commands to appropriate handlers (screenshot manager, hardware controller).
"""

from __future__ import annotations

import logging
from typing import Any, Dict, Optional, Tuple

from .hardware_controller import HardwareController
from .screenshot_manager import ScreenshotManager

logger = logging.getLogger(__name__)


class CommandExecutor:
    """Executes commands by delegating to appropriate handlers."""

    def __init__(self, hardware_controller: HardwareController):
        """
        Initialize command executor.

        Args:
            hardware_controller: Hardware controller instance
        """
        self.hardware = hardware_controller
        self.screenshot_manager = ScreenshotManager(hardware_controller)

    def execute(
        self,
        command_name: str,
        payload: Dict[str, Any],
        screenshot_upload_url: str = "",
        client_key: str = "",
        insecure_ssl: bool = False,
    ) -> Tuple[bool, str, Optional[Dict[str, Any]]]:
        """
        Execute a command, routing to appropriate handler.

        Args:
            command_name: Command identifier
            payload: Command parameters
            screenshot_upload_url: URL for screenshot upload (for screenshot command)
            client_key: Client authentication key (for screenshot upload)
            insecure_ssl: Whether to skip SSL verification (for screenshot upload)

        Returns:
            Tuple of (success: bool, message: str, result: Optional[Dict])
        """
        try:
            # Common commands
            if command_name in ("get_metrics", "getMetrics", "get-metrics"):
                metrics = self.hardware.get_metrics()
                if not metrics.get("supported", False):
                    return False, str(metrics.get("message") or "metrics_not_supported"), metrics
                return True, "ok", metrics

            if command_name in ("screenshot", "getScreenshot", "get_screenshot"):
                if not screenshot_upload_url:
                    return False, "screenshot_upload_url not configured", None
                if not client_key:
                    return False, "client_key not configured", None

                result = self.screenshot_manager.capture_and_upload(
                    payload, screenshot_upload_url, client_key, insecure_ssl
                )
                return True, "ok", result

            # State management commands
            if command_name in ("get_state", "getState", "get-state"):
                state = self.hardware.get_current_state()
                return True, "ok", {"state": state}

            if command_name in ("set_state", "setState", "set-state"):
                state_name = str(payload.get("state") or payload.get("state_name", ""))
                if not state_name:
                    return False, "Missing 'state' or 'state_name' in payload", None
                try:
                    self.hardware.set_state(state_name)
                    return True, "ok", {"state": state_name}
                except ValueError as e:
                    return False, str(e), None

            # Hardware-specific commands (delegated to hardware controller)
            return self.hardware.execute_command(command_name, payload)

        except Exception as e:
            logger.exception("Error executing command %s", command_name)
            return False, str(e), None
