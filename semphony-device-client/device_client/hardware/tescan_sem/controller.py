"""
TESCAN SEM hardware controller implementation.
"""

from __future__ import annotations

import logging
import os
import pyautogui
import time
from typing import Any, Dict, Optional, Tuple

from ..base import BaseHardwareController
from .buttons import TescanButtonConfig
from .states import TescanStateConfig
from .metrics import TescanMetricsReader
from ...utils.button_utils import ButtonValidationError

logger = logging.getLogger(__name__)


class TescanSemController(BaseHardwareController):
    """TESCAN SEM hardware controller."""

    def __init__(self):
        """Initialize TESCAN SEM controller."""
        super().__init__()
        self.button_config = TescanButtonConfig()
        self.state_config = TescanStateConfig()
        self.metrics_reader = TescanMetricsReader()
        # Initialize to default state
        self._current_state = self.state_config.get_default_state()
        self._state_config_dict = self.state_config.get_config()

    @property
    def hardware_mode(self) -> str:
        """Return hardware mode identifier."""
        return "tescan_sem"

    @property
    def hardware_name(self) -> str:
        """Return human-readable hardware name."""
        return "TESCAN SEM"

    def initialize(self) -> None:
        """Initialize TESCAN SEM controller and check SDK connectivity."""
        logger.info("Initializing TESCAN SEM controller...")
        
        # Check SDK connectivity if in tescan_mira3 mode
        mode = (os.getenv("HARDWARE_MODE") or os.getenv("SEM_MODE", "gui")).strip().lower()
        if mode == "tescan_sem" or mode == "tescan_mira3":
            # Try to check connectivity, but don't fail if SDK is not available
            if self.metrics_reader.check_connectivity():
                logger.info("TESCAN SDK connectivity check passed")
            else:
                logger.warning("TESCAN SDK connectivity check failed (continuing in GUI-only mode)")
        
        self._current_state = self.state_config.get_default_state()

    def get_metrics(self) -> Dict[str, Any]:
        """Get TESCAN SEM metrics via SDK."""
        return self.metrics_reader.get_metrics()

    def get_button_config(self) -> Dict[str, Any]:
        """Return merged button configuration (for backward compatibility)."""
        return self.button_config.get_config()

    def get_state_config(self) -> Dict[str, Any]:
        """Return state configuration."""
        return self._state_config_dict

    def get_current_state(self) -> str:
        """Return current GUI state."""
        return self._current_state or self.state_config.get_default_state()

    def set_state(self, state_name: str) -> None:
        """
        Change the GUI state.

        For TESCAN, only "default" state is supported (no state switching needed).

        Args:
            state_name: Name of the state to switch to

        Raises:
            ValueError: If state_name is not a valid state
        """
        available_states = self.state_config.get_available_states()
        if state_name not in available_states:
            raise ValueError(
                f"Unknown state: {state_name}. Available states: {available_states}"
            )

        if self._current_state == state_name:
            logger.debug(f"Already in state '{state_name}'")
            return

        # For TESCAN, only default state exists, so just update the state variable
        # No actual GUI state switching is needed
        logger.info(f"Setting state to '{state_name}' (TESCAN only supports default state)")
        self._current_state = state_name

    def validate_button(self, button_name: str, state: Optional[str] = None) -> Tuple[Dict[str, Any], Dict[str, int]]:
        """
        Validate that a button exists and is available in the given state.

        Args:
            button_name: Name of the button to validate
            state: Optional state name. If None, uses current state.

        Returns:
            Tuple of (button_info dict, center dict with x, y coordinates)

        Raises:
            ButtonValidationError: If button is invalid or not available in state
        """
        if state is None:
            state = self.get_current_state()
        return self.button_config.validate_button(button_name, state)

    def execute_command(self, command_name: str, payload: Dict[str, Any]) -> Tuple[bool, str, Optional[Dict[str, Any]]]:
        """
        Execute TESCAN-specific commands.

        Args:
            command_name: Command identifier
            payload: Command parameters

        Returns:
            Tuple of (success: bool, message: str, result: Optional[Dict])
        """
        try:
            if command_name in ("gotoButton", "goto_button"):
                button_name = str(payload["button_name"])
                duration = float(payload.get("duration", 0.3))

                # For TESCAN, all buttons are in the default state, so no state checking needed
                button_info, center = self.validate_button(button_name)
                pyautogui.moveTo(center["x"], center["y"], duration=duration)
                return True, "ok", None

            if command_name in ("clickButton", "click_button"):
                button_name = str(payload["button_name"])
                duration = float(payload.get("duration", 0.3))
                clicks = int(payload.get("clicks", 1))
                interval = float(payload.get("interval", 0.1))
                button = str(payload.get("button", "left"))
                confirmation_wait = float(payload.get("confirmation_wait", 1.0))

                # For TESCAN, all buttons are in the default state, so no state checking needed
                button_info, center = self.validate_button(button_name)
                pyautogui.moveTo(center["x"], center["y"], duration=duration)
                pyautogui.click(center["x"], center["y"], clicks=clicks, interval=interval, button=button)

                # Check if button requires confirmation
                if button_info.get("requires_confirmation", False):
                    time.sleep(confirmation_wait)
                    pyautogui.press("enter")

                # For TESCAN, no state switching is needed (only default state exists)

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

            return False, f"Unknown command: {command_name}", None

        except ButtonValidationError as e:
            return False, e.message, None
        except Exception as e:
            logger.exception("Error executing TESCAN command %s", command_name)
            return False, str(e), None
