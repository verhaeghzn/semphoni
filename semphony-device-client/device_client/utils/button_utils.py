"""
Utility functions for button operations and validation.
"""

from typing import Dict, Any, Tuple


class ButtonValidationError(Exception):
    """Exception raised when button validation fails."""
    def __init__(self, message: str, status_code: int = 400):
        self.message = message
        self.status_code = status_code
        super().__init__(self.message)


def validate_button(button_name: str, buttons: Dict[str, Any]) -> Tuple[Dict[str, Any], Dict[str, int]]:
    """
    Validate that a button exists, is configured, and has valid coordinates.

    Args:
        button_name: Name of the button to validate
        buttons: Dictionary of button configurations

    Returns:
        tuple: (button_info dict, center dict with x, y coordinates)

    Raises:
        ButtonValidationError: If button is invalid, missing, or not configured
    """
    # Check if button exists in configuration
    if button_name not in buttons:
        raise ButtonValidationError(
            f"Button '{button_name}' not found in configuration",
            status_code=404
        )

    button_info = buttons[button_name]

    # Check if button is configured (not None)
    if button_info is None:
        raise ButtonValidationError(
            f"Button '{button_name}' is not configured (set to None)",
            status_code=400
        )

    # Check if button has center coordinates
    center = button_info.get("center")
    if not center:
        raise ButtonValidationError(
            f"Button '{button_name}' has no center coordinates",
            status_code=400
        )

    # Check if center coordinates are valid
    x = center.get("x")
    y = center.get("y")

    if x is None or y is None:
        raise ButtonValidationError(
            f"Button '{button_name}' has invalid center coordinates",
            status_code=400
        )

    # Check if button is enabled (if enabled property exists)
    if "enabled" in button_info and not button_info.get("enabled", True):
        raise ButtonValidationError(
            f"Button '{button_name}' is disabled",
            status_code=403
        )

    return button_info, {"x": x, "y": y}


def get_button_coordinates(button_name: str, buttons: Dict[str, Any]) -> Dict[str, int]:
    """
    Get the center coordinates of a button.

    Args:
        button_name: Name of the button
        buttons: Dictionary of button configurations

    Returns:
        dict: {"x": x, "y": y}
    """
    _, center = validate_button(button_name, buttons)
    return center
