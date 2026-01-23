"""
Base hardware controller implementation with common functionality.

Provides state management and automatic state switching for all hardware implementations.
"""

from __future__ import annotations

import logging
from typing import Any, Dict, Optional, Tuple

from ..core.hardware_controller import HardwareController
from ..utils.button_utils import ButtonValidationError

logger = logging.getLogger(__name__)


class BaseHardwareController(HardwareController):
    """Base implementation with common functionality for all hardware controllers."""

    def __init__(self):
        """Initialize base controller with state management."""
        self._current_state: Optional[str] = None
        self._state_config: Optional[Dict[str, Any]] = None

    def _ensure_state(self, required_state: Optional[str]) -> None:
        """
        Ensure the hardware is in the required state, switching if necessary.

        Args:
            required_state: State name required for the operation, or None if any state is OK

        Raises:
            ValueError: If required_state is not a valid state
        """
        if required_state is None:
            # No state requirement, current state is fine
            return

        current = self.get_current_state()
        if current == required_state:
            # Already in the correct state
            return

        # Need to switch states
        logger.info(f"Switching from state '{current}' to '{required_state}'")
        self.set_state(required_state)

    def _update_state_after_command(self, button_name: str) -> None:
        """
        Update current state if the button clicked was a state-switch button.

        Args:
            button_name: Name of the button that was clicked
        """
        state_config = self.get_state_config()
        state_switch_buttons = state_config.get("state_switch_buttons", {})

        # Check if this button switches to a state
        for state_name, switch_button in state_switch_buttons.items():
            if switch_button == button_name:
                logger.info(f"Button '{button_name}' switched to state '{state_name}'")
                self._current_state = state_name
                return

    def get_screenshot_config(self) -> Dict[str, Any]:
        """Return screenshot configuration."""
        import os
        return {
            "monitor_number": int(os.getenv("SEMPC_MONITOR_NUMBER", "2")),
            "jpeg_quality": int(os.getenv("SCREENSHOT_JPEG_QUALITY", "75")),
        }
