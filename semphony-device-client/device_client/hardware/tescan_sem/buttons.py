"""
Button configuration for TESCAN SEM hardware, organized by state.
"""

from typing import Dict, Any, Optional
import logging
import json
import os
from pathlib import Path

logger = logging.getLogger(__name__)

# Common buttons available in all states (for TESCAN, all buttons are common since there's only one state)
COMMON_BUTTONS: Dict[str, Any] = {
    "beam_on_off_toggle": {
        "bbox": {"x1": 3519, "y1": 861, "x2": 3799, "y2": 888},
        "center": {"x": 3659, "y": 874},
        "notes": "Electron Beam section, button labeled 'BEAM ON'.",
    },
    "vacuum_vent": {
        "bbox": {"x1": 3640, "y1": 1047, "x2": 3735, "y2": 1075},
        "center": {"x": 3688, "y": 1061},
        "notes": "Vacuum panel bottom row, grey 'VENT' button.",
        "requires_confirmation": True,
    },
    "vacuum_pump": {
        "bbox": {"x1": 3677, "y1": 1050, "x2": 3896, "y2": 1078},
        "center": {"x": 3787, "y": 1064},
        "notes": "Vacuum panel bottom row, blue 'PUMP' button.",
    },
    "rbse_push_in": {
        "bbox": {"x1": 3556, "y1": 767, "x2": 3676, "y2": 792},
        "center": {"x": 3616, "y": 780},
        "notes": "Motorized RBSE panel, left button 'Push In'.",
    },
    "rbse_pull_out": {
        "bbox": {"x1": 3633, "y1": 762, "x2": 3753, "y2": 787},
        "center": {"x": 3693, "y": 775},
        "notes": "Motorized RBSE panel, middle button 'Pull Out'.",
    },
    "detector_mix_a": {
        "bbox": {"x1": 3569, "y1": 549, "x2": 3599, "y2": 569},
        "center": {"x": 3584, "y": 559},
        "notes": "SEM Detectors & Mixer row, radio 'A'.",
    },
    "detector_mix_a_plus_b": {
        "bbox": {"x1": 3599, "y1": 550, "x2": 3639, "y2": 570},
        "center": {"x": 3619, "y": 560},
        "notes": "SEM Detectors & Mixer row, radio 'A+B'.",
    },
    "detector_mix_a_min_b": {
        "bbox": {"x1": 3646, "y1": 550, "x2": 3686, "y2": 570},
        "center": {"x": 3666, "y": 560},
        "notes": "SEM Detectors & Mixer row, radio 'A-B'.",
    },
    "detector_mix_a_and_b": {
        "bbox": {"x1": 3691, "y1": 547, "x2": 3731, "y2": 567},
        "center": {"x": 3711, "y": 557},
        "notes": "SEM Detectors & Mixer row, radio 'A|B'.",
    },
    "detector_mix_abcd": {
        "bbox": {"x1": 3735, "y1": 551, "x2": 3790, "y2": 571},
        "center": {"x": 3763, "y": 561},
        "notes": "SEM Detectors & Mixer row, radio 'AB|CD'.",
    },
    "trigger_degauss": {
        "bbox": {"x1": 3579, "y1": 108, "x2": 3614, "y2": 143},
        "center": {"x": 3597, "y": 126},
        "notes": "Top-right main toolbar; degauss icon location is approximate.",
    },
    "acquire": {
        "bbox": {"x1": 3738, "y1": 296, "x2": 3808, "y2": 316},
        "center": {"x": 3773, "y": 306},
        "notes": "Info Panel tabs row, 'Acquire'.",
    },
    "continual_mode": {
        "bbox": {"x1": 3565, "y1": 297, "x2": 3655, "y2": 317},
        "center": {"x": 3610, "y": 307},
        "notes": "Info Panel tabs row, 'Continual'.",
    },
    "single_mode": {
        "bbox": {"x1": 3662, "y1": 297, "x2": 3742, "y2": 317},
        "center": {"x": 3702, "y": 307},
        "notes": "Info Panel tabs row, 'Single'.",
    },
    "stage_control_stop": {
        "bbox": {"x1": 2163, "y1": 924, "x2": 2238, "y2": 946},
        "center": {"x": 2201, "y": 935},
        "notes": "Stage Control panel bottom-left, 'Stop' button.",
    },
}

# Buttons organized by state (for TESCAN, only default state exists)
BUTTONS_BY_STATE: Dict[str, Dict[str, Any]] = {
    "default": {
        # All TESCAN buttons are in common_buttons, but state-specific buttons can be added here if needed
    },
}

# Default image size
DEFAULT_IMAGE_SIZE = {"width": 1920, "height": 1080}


def get_buttons_config_override_path() -> Path:
    """
    Path to an optional on-disk override for button configuration.

    Override with env var `SEM_BUTTONS_CONFIG_PATH` (for backward compatibility).
    """
    raw = (os.getenv("SEM_BUTTONS_CONFIG_PATH") or "").strip()
    if raw:
        return Path(raw).expanduser()

    # Default: <repo>/semphony-device-client/data/buttons_config.json
    repo_dir = Path(__file__).resolve().parents[2]
    return repo_dir / "data" / "buttons_config.json"


def load_buttons_config_override(default_config: Dict[str, Any]) -> Dict[str, Any]:
    """
    Load a button configuration override from disk if present; otherwise return default.

    Supports both old format (flat buttons dict) and new format (buttons_by_state).
    """
    path = get_buttons_config_override_path()
    try:
        if not path.is_file():
            return default_config
        with path.open("r", encoding="utf-8") as f:
            data = json.load(f)
        
        # Check if it's the old format (flat buttons dict)
        if "image_size" in data and "buttons" in data and "buttons_by_state" not in data:
            # Convert old format to new format
            logger.info("Converting old button config format to new state-based format")
            old_buttons = data["buttons"]
            # All buttons go to common_buttons in new format
            new_config = {
                "image_size": data["image_size"],
                "buttons_by_state": {},
                "common_buttons": old_buttons,
            }
            return new_config
        
        # New format
        if "image_size" not in data or "buttons_by_state" not in data or "common_buttons" not in data:
            raise ValueError("override file has unexpected shape")
        logger.info("Loaded TESCAN button configuration override from %s", str(path))
        return data
    except Exception as e:
        logger.warning("Failed to load TESCAN button configuration override from %s: %s", str(path), e)
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
    logger.info("Saved TESCAN button configuration override to %s", str(path))
    return path


class TescanButtonConfig:
    """Button configuration manager for TESCAN SEM."""

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
