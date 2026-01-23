"""
KW-DDS hardware controller implementation.
"""

from __future__ import annotations

import logging
from typing import Any, Dict, Optional, Tuple

from ..base import BaseHardwareController
from .buttons import KwDdsButtonConfig
from .states import KwDdsStateConfig
from ...utils.button_utils import ButtonValidationError

logger = logging.getLogger(__name__)


class KwDdsController(BaseHardwareController):
    """KW-DDS hardware controller (GUI automation only)."""

    def __init__(self):
        """Initialize KW-DDS controller."""
        super().__init__()
        self.button_config = KwDdsButtonConfig()
        self.state_config = KwDdsStateConfig()
        # Initialize to default state
        self._current_state = self.state_config.get_default_state()
        self._state_config_dict = self.state_config.get_config()

    @property
    def hardware_mode(self) -> str:
        """Return hardware mode identifier."""
        return "kw_dds"

    @property
    def hardware_name(self) -> str:
        """Return human-readable hardware name."""
        return "Kammrath Weiss DDS"

    def initialize(self) -> None:
        """Initialize KW-DDS controller."""
        logger.info("KW-DDS controller initialized (GUI automation mode)")
        self._current_state = self.state_config.get_default_state()

    def get_metrics(self) -> Dict[str, Any]:
        """
        Get KW-DDS metrics.

        Returns:
            Dict indicating metrics are not supported (GUI automation only).
        """
        return {
            "hardware_mode": "kw_dds",
            "supported": False,
            "message": "KW-DDS metrics not available (GUI automation only).",
        }

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

        # Get the button that switches to this state
        switch_button = self.state_config.get_state_switch_button(state_name)
        if not switch_button:
            # No switch button defined, just update state
            self._current_state = state_name
            return

        # Validate and click the switch button
        try:
            button_info, center = self.button_config.validate_button(switch_button, self._current_state)
            logger.info(f"Switching to state '{state_name}' by clicking '{switch_button}'")
            import pyautogui
            import time
            pyautogui.moveTo(center["x"], center["y"], duration=0.3)
            pyautogui.click(center["x"], center["y"])
            time.sleep(0.5)
            self._current_state = state_name
        except ButtonValidationError as e:
            raise ValueError(f"Cannot switch to state '{state_name}': {e.message}") from e

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
        Execute KW-DDS-specific commands.

        Args:
            command_name: Command identifier
            payload: Command parameters

        Returns:
            Tuple of (success: bool, message: str, result: Optional[Dict])
        """
        return False, f"Command '{command_name}' not yet implemented for KW-DDS", None
