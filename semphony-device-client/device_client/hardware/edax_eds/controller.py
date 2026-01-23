"""
EDAX EDS hardware controller implementation.
"""

from __future__ import annotations

import logging
import pyautogui
import time
from typing import Any, Dict, Optional, Tuple

from ..base import BaseHardwareController
from .buttons import EdaxButtonConfig
from .states import EdaxStateConfig
from ...utils.button_utils import ButtonValidationError

logger = logging.getLogger(__name__)


class EdaxEdsController(BaseHardwareController):
    """EDAX EDS hardware controller (GUI automation only)."""

    def __init__(self):
        """Initialize EDAX EDS controller."""
        super().__init__()
        self.button_config = EdaxButtonConfig()
        self.state_config = EdaxStateConfig()
        # Initialize to default state
        self._current_state = self.state_config.get_default_state()
        self._state_config_dict = self.state_config.get_config()

    @property
    def hardware_mode(self) -> str:
        """Return hardware mode identifier."""
        return "edax_eds"

    @property
    def hardware_name(self) -> str:
        """Return human-readable hardware name."""
        return "EDAX EDS"

    def initialize(self) -> None:
        """Initialize EDAX EDS controller."""
        # For GUI-only mode, we just ensure state is set
        logger.info("EDAX EDS controller initialized (GUI automation mode)")
        self._current_state = self.state_config.get_default_state()

    def get_metrics(self) -> Dict[str, Any]:
        """
        Get EDAX EDS metrics.

        Returns:
            Dict indicating metrics are not supported (GUI automation only).
        """
        return {
            "hardware_mode": "edax_eds",
            "supported": False,
            "message": "EDAX EDS metrics not available (GUI automation only).",
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
        Change the GUI state by clicking the appropriate tab button.

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
            raise ValueError(f"No switch button defined for state: {state_name}")

        # Validate and click the switch button
        try:
            button_info, center = self.button_config.validate_button(switch_button, self._current_state)
            logger.info(f"Switching to state '{state_name}' by clicking '{switch_button}'")
            pyautogui.moveTo(center["x"], center["y"], duration=0.3)
            pyautogui.click(center["x"], center["y"])
            # Wait a bit for the state change to take effect
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
        Execute EDAX-specific commands.

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

                # Ensure we're in the correct state for this button
                current_state = self.get_current_state()
                buttons_in_state = self.button_config.get_buttons_for_state(current_state)
                if button_name not in buttons_in_state:
                    # Button not in current state, check which state it's in
                    required_state = None
                    for state, buttons in self.button_config.buttons_by_state.items():
                        if button_name in buttons:
                            required_state = state
                            break
                    if required_state:
                        self._ensure_state(required_state)
                    elif button_name not in self.button_config.common_buttons:
                        return False, f"Button '{button_name}' not found in any state", None

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

                # Ensure we're in the correct state for this button
                current_state = self.get_current_state()
                buttons_in_state = self.button_config.get_buttons_for_state(current_state)
                if button_name not in buttons_in_state:
                    # Button not in current state, check which state it's in
                    required_state = None
                    for state, buttons in self.button_config.buttons_by_state.items():
                        if button_name in buttons:
                            required_state = state
                            break
                    if required_state:
                        self._ensure_state(required_state)
                    elif button_name not in self.button_config.common_buttons:
                        return False, f"Button '{button_name}' not found in any state", None

                button_info, center = self.validate_button(button_name)
                pyautogui.moveTo(center["x"], center["y"], duration=duration)
                pyautogui.click(center["x"], center["y"], clicks=clicks, interval=interval, button=button)

                # Check if button requires confirmation
                if button_info.get("requires_confirmation", False):
                    time.sleep(confirmation_wait)
                    pyautogui.press("enter")

                # Update state if this was a state-switch button
                self._update_state_after_command(button_name)

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
            logger.exception("Error executing EDAX command %s", command_name)
            return False, str(e), None
