"""
Abstract base class for hardware controllers.

All hardware implementations (TESCAN SEM, EDAX EDS, KW-DDS) must implement
this interface to provide a consistent API for command execution, metrics,
and state management.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Any, Dict, Optional, Tuple


class HardwareController(ABC):
    """Abstract base class for hardware-specific controllers."""

    @property
    @abstractmethod
    def hardware_mode(self) -> str:
        """Return the hardware mode identifier (e.g., 'tescan_sem', 'edax_eds')."""
        pass

    @property
    @abstractmethod
    def hardware_name(self) -> str:
        """Return human-readable hardware name."""
        pass

    @abstractmethod
    def initialize(self) -> None:
        """Initialize the hardware connection (SDK, GUI detection, etc.)."""
        pass

    @abstractmethod
    def get_metrics(self) -> Dict[str, Any]:
        """
        Retrieve hardware telemetry/metrics.

        Returns:
            Dict with 'supported' key (bool) and hardware-specific metrics.
            If not supported, returns {'supported': False, 'message': '...'}.
        """
        pass

    @abstractmethod
    def get_button_config(self) -> Dict[str, Any]:
        """
        Return button configuration for this hardware.

        Returns:
            Dict with 'image_size' and 'buttons' keys matching current format.
            For backward compatibility, this should merge all buttons from all states.
        """
        pass

    @abstractmethod
    def get_state_config(self) -> Dict[str, Any]:
        """
        Return state configuration for this hardware.

        Returns:
            Dict with 'states', 'state_switch_buttons', and 'default_state' keys.
        """
        pass

    @abstractmethod
    def get_current_state(self) -> str:
        """
        Return the current GUI state identifier.

        Returns:
            String identifier of the current state (e.g., 'acquire', 'spectrum').
        """
        pass

    @abstractmethod
    def set_state(self, state_name: str) -> None:
        """
        Change the GUI state (e.g., switch tabs).

        Args:
            state_name: Name of the state to switch to

        Raises:
            ValueError: If state_name is not a valid state
        """
        pass

    @abstractmethod
    def validate_button(self, button_name: str, state: Optional[str] = None) -> Tuple[Dict[str, Any], Dict[str, int]]:
        """
        Validate that a button exists and is available in the given state.

        Args:
            button_name: Name of the button to validate
            state: Optional state name. If None, uses current state.

        Returns:
            Tuple of (button_info dict, center dict with x, y coordinates)

        Raises:
            ButtonValidationError: If button is invalid, missing, or not available in state
        """
        pass

    @abstractmethod
    def execute_command(self, command_name: str, payload: Dict[str, Any]) -> Tuple[bool, str, Optional[Dict[str, Any]]]:
        """
        Execute a hardware-specific command.

        Args:
            command_name: Command identifier
            payload: Command parameters

        Returns:
            Tuple of (success: bool, message: str, result: Optional[Dict])
        """
        pass

    def get_screenshot_config(self) -> Dict[str, Any]:
        """
        Return screenshot configuration (monitor number, etc.).
        Can be overridden by hardware implementations.

        Returns:
            Dict with 'monitor_number' and 'jpeg_quality' keys.
        """
        import os
        return {
            "monitor_number": int(os.getenv("SEMPC_MONITOR_NUMBER", "2")),
            "jpeg_quality": int(os.getenv("SCREENSHOT_JPEG_QUALITY", "75")),
        }
