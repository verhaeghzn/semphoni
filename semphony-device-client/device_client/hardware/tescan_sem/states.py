"""
State configuration for TESCAN SEM hardware.
"""

from typing import Dict, Any

DEFAULT_STATE_CONFIG: Dict[str, Any] = {
    "states": ["default"],
    "state_switch_buttons": {},
    "default_state": "default",
}


class TescanStateConfig:
    """State configuration manager for TESCAN SEM."""

    def __init__(self, config: Dict[str, Any] = None):
        """
        Initialize state configuration.

        Args:
            config: State configuration dict. If None, uses DEFAULT_STATE_CONFIG.
        """
        self.config = config or DEFAULT_STATE_CONFIG.copy()

    def get_config(self) -> Dict[str, Any]:
        """Return state configuration dict."""
        return self.config

    def get_state_switch_button(self, state_name: str) -> str:
        """
        Get the button name that switches to the given state.

        Args:
            state_name: Name of the state

        Returns:
            Button name that switches to the state

        Raises:
            ValueError: If state_name is not a valid state
        """
        states = self.config.get("states", [])
        if state_name not in states:
            raise ValueError(f"Unknown state: {state_name}. Available states: {states}")

        switch_buttons = self.config.get("state_switch_buttons", {})
        return switch_buttons.get(state_name, "")

    def get_available_states(self) -> list[str]:
        """Return list of available state names."""
        return list(self.config.get("states", []))

    def get_default_state(self) -> str:
        """Return default state name."""
        return self.config.get("default_state", "")
