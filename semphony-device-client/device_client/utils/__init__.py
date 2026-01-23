"""
Shared utility functions for hardware implementations.
"""

from .button_utils import ButtonValidationError, validate_button, get_button_coordinates

__all__ = ["ButtonValidationError", "validate_button", "get_button_coordinates"]
