"""
Utility functions for button operations and validation.
"""
from .config import BUTTONS


class ButtonValidationError(Exception):
    """Exception raised when button validation fails."""
    def __init__(self, message, status_code=400):
        self.message = message
        self.status_code = status_code
        super().__init__(self.message)


def validate_button(button_name):
    """
    Validate that a button exists, is configured, and has valid coordinates.
    
    Args:
        button_name: Name of the button to validate
        
    Returns:
        tuple: (button_info dict, center dict with x, y coordinates)
        
    Raises:
        ButtonValidationError: If button is invalid, missing, or not configured
    """
    # Check if button exists in configuration
    if button_name not in BUTTONS:
        raise ButtonValidationError(
            f"Button '{button_name}' not found in configuration",
            status_code=404
        )
    
    button_info = BUTTONS[button_name]
    
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


def get_button_coordinates(button_name):
    """
    Get the center coordinates of a button.
    
    Args:
        button_name: Name of the button
        
    Returns:
        dict: {"x": x, "y": y}
    """
    _, center = validate_button(button_name)
    return center

