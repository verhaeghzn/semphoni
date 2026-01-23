"""
Button configuration constants for device control interface.

This module contains the button locations and properties that can be used
for programmatic control of the device interface via GUI automation.
"""

from __future__ import annotations

import json
import logging
import os
from pathlib import Path
from typing import Any, Dict

logger = logging.getLogger(__name__)


def get_buttons_config_override_path() -> Path:
    """
    Path to an optional on-disk override for BUTTONS_CONFIG.

    This allows calibration to persist across code updates without editing
    Python source files.

    Override with env var `SEM_BUTTONS_CONFIG_PATH`.
    """
    raw = (os.getenv("SEM_BUTTONS_CONFIG_PATH") or "").strip()
    if raw:
        return Path(raw).expanduser()

    # Default: <repo>/semphony-device-client/data/buttons_config.json
    repo_dir = Path(__file__).resolve().parents[1]
    return repo_dir / "data" / "buttons_config.json"


def _is_buttons_config_shape(obj: Any) -> bool:
    if not isinstance(obj, dict):
        return False
    if "image_size" not in obj or "buttons" not in obj:
        return False
    if not isinstance(obj.get("image_size"), dict):
        return False
    if not isinstance(obj.get("buttons"), dict):
        return False
    return True


def load_buttons_config_override(default_config: Dict[str, Any]) -> Dict[str, Any]:
    """
    Load a BUTTONS_CONFIG override from disk if present; otherwise return default.
    """
    path = get_buttons_config_override_path()
    try:
        if not path.is_file():
            return default_config
        with path.open("r", encoding="utf-8") as f:
            data = json.load(f)
        if not _is_buttons_config_shape(data):
            raise ValueError("override file has unexpected shape (expected keys: image_size, buttons)")
        logger.info("Loaded BUTTONS_CONFIG override from %s", str(path))
        return data
    except Exception as e:
        logger.warning("Failed to load BUTTONS_CONFIG override from %s: %s", str(path), e)
        return default_config


def save_buttons_config_override(config: Dict[str, Any]) -> Path:
    """
    Persist a BUTTONS_CONFIG dict to the override path as JSON (atomic write).
    """
    path = get_buttons_config_override_path()
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    with tmp.open("w", encoding="utf-8") as f:
        json.dump(config, f, indent=2, sort_keys=True)
        f.write("\n")
    tmp.replace(path)
    logger.info("Saved BUTTONS_CONFIG override to %s", str(path))
    return path


DEFAULT_BUTTONS_CONFIG: Dict[str, Any] = {
    "image_size": {"width": 1920, "height": 1080},
    "buttons": {
        "beam_on_off_toggle": {
            "bbox": {"x1": 3519, "y1": 861, "x2": 3799, "y2": 888},
            "center": {"x": 3659, "y": 874},
            "notes": "Electron Beam section, button labeled 'BEAM ON'.",
        },
        "vacuum_stndby": None,
        "vacuum_vent": {
            "bbox": {"x1": 3640, "y1": 1047, "x2": 3735, "y2": 1075},
            "center": {"x": 3688, "y": 1061},
            "notes": "Vacuum panel bottom row, grey 'VENT' button.",
            "requires_confirmation": True
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
            "notes": "Stage Control panel bottom-left, 'Stop' button."
        },
    },
}

# Load a persisted calibration if present.
BUTTONS_CONFIG = load_buttons_config_override(DEFAULT_BUTTONS_CONFIG)

# Convenience accessors
BUTTONS = BUTTONS_CONFIG["buttons"]
IMAGE_SIZE = BUTTONS_CONFIG["image_size"]

