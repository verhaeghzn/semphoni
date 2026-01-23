"""
Button configuration for KW-DDS hardware, organized by state.
"""

from typing import Dict, Any, Optional
import logging
import json
import os
from pathlib import Path

logger = logging.getLogger(__name__)

# Common buttons available in all states
COMMON_BUTTONS: Dict[str, Any] = {}

# Buttons organized by state
BUTTONS_BY_STATE: Dict[str, Dict[str, Any]] = {
    "default": {},
}

# Default image size
DEFAULT_IMAGE_SIZE = {"width": 1920, "height": 1080}


def get_buttons_config_override_path() -> Path:
    """
    Path to an optional on-disk override for button configuration.

    Override with env var `KW_DDS_BUTTONS_CONFIG_PATH`.
    """
    raw = (os.getenv("KW_DDS_BUTTONS_CONFIG_PATH") or "").strip()
    if raw:
        return Path(raw).expanduser()

    # Default: <repo>/semphony-device-client/data/kw_dds_buttons_config.json
    repo_dir = Path(__file__).resolve().parents[2]
    return repo_dir / "data" / "kw_dds_buttons_config.json"


def load_buttons_config_override(default_config: Dict[str, Any]) -> Dict[str, Any]:
    """
    Load a button configuration override from disk if present; otherwise return default.
    """
    path = get_buttons_config_override_path()
    try:
        if not path.is_file():
            return default_config
        with path.open("r", encoding="utf-8") as f:
            data = json.load(f)
        if "image_size" not in data or "buttons_by_state" not in data or "common_buttons" not in data:
            raise ValueError("override file has unexpected shape")
        logger.info("Loaded KW-DDS button configuration override from %s", str(path))
        return data
    except Exception as e:
        logger.warning("Failed to load KW-DDS button configuration override from %s: %s", str(path), e)
        return default_config


def save_buttons_config_override(config: Dict[str, Any]) -> Path:
    """
    Persist a button configuration dict to the override path as JSON (atomic write).
    """
    path = get_buttons_config_override_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    with tmp.open("w", encoding="utf-8") as f:
        json.dump(config, f, indent=2, sort_keys=True)
        f.write("\n")
    tmp.replace(path)
    logger.info("Saved KW-DDS button configuration override to %s", str(path))
    return path


class KwDdsButtonConfig:
    """Button configuration manager for KW-DDS."""

    def __init__(self):
        """Initialize button configuration."""
        default_config = {
            "image_size": DEFAULT_IMAGE_SIZE,
            "buttons_by_state": BUTTONS_BY_STATE,
            "common_buttons": COMMON_BUTTONS,
        }
        self.config = load_buttons_config_override(default_config)
        self.image_size = self.config["image_size"]
        self.buttons_by_state = self.config["buttons_by_state"]
        self.common_buttons = self.config["common_buttons"]

    def get_config(self) -> Dict[str, Any]:
        """
        Return merged button configuration (for backward compatibility).

        Returns:
            Dict with 'image_size' and 'buttons' keys (all buttons merged).
        """
        # Merge all buttons from all states plus common buttons
        all_buttons = self.common_buttons.copy()
        for state_buttons in self.buttons_by_state.values():
            all_buttons.update(state_buttons)

        return {
            "image_size": self.image_size,
            "buttons": all_buttons,
        }

    def get_buttons_for_state(self, state_name: str) -> Dict[str, Any]:
        """
        Get buttons available in a specific state.

        Args:
            state_name: Name of the state

        Returns:
            Dict of buttons available in that state (includes common buttons).
        """
        state_buttons = self.buttons_by_state.get(state_name, {}).copy()
        # Merge with common buttons
        state_buttons.update(self.common_buttons)
        return state_buttons

    def get_all_buttons(self) -> Dict[str, Any]:
        """Return all buttons across all states."""
        return self.get_config()["buttons"]

    def validate_button(self, button_name: str, state: Optional[str] = None) -> tuple[Dict[str, Any], Dict[str, int]]:
        """
        Validate that a button exists and is available in the given state.

        Args:
            button_name: Name of the button to validate
            state: Optional state name. If None, checks all buttons.

        Returns:
            Tuple of (button_info dict, center dict with x, y coordinates)

        Raises:
            ButtonValidationError: If button is invalid or not available in state
        """
        from ...utils.button_utils import ButtonValidationError, validate_button as validate_button_impl

        if state:
            # Check buttons for specific state
            buttons = self.get_buttons_for_state(state)
        else:
            # Check all buttons
            buttons = self.get_all_buttons()

        return validate_button_impl(button_name, buttons)
