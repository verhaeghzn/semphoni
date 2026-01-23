"""
Hardware implementations factory.

Creates the appropriate hardware controller based on environment configuration.
"""

from __future__ import annotations

import logging
import os
from typing import TYPE_CHECKING

if TYPE_CHECKING:
    from ..core.hardware_controller import HardwareController

logger = logging.getLogger(__name__)


def create_hardware_controller() -> "HardwareController":
    """
    Factory function to create the appropriate hardware controller.

    Reads HARDWARE_MODE environment variable (or SEM_MODE for backward compatibility).

    Returns:
        HardwareController instance for the configured hardware mode

    Raises:
        ValueError: If hardware mode is unknown
    """
    # Check HARDWARE_MODE first, fall back to SEM_MODE for backward compatibility
    mode = (os.getenv("HARDWARE_MODE") or os.getenv("SEM_MODE", "tescan_sem")).strip().lower()

    if mode == "tescan_sem":
        from .tescan_sem.controller import TescanSemController
        logger.info("Creating TESCAN SEM hardware controller")
        return TescanSemController()
    elif mode == "edax_eds":
        from .edax_eds.controller import EdaxEdsController
        logger.info("Creating EDAX EDS hardware controller")
        return EdaxEdsController()
    elif mode == "kw_dds":
        from .kw_dds.controller import KwDdsController
        logger.info("Creating KW-DDS hardware controller")
        return KwDdsController()
    else:
        raise ValueError(
            f"Unknown hardware mode: {mode}. "
            f"Supported modes: tescan_sem, edax_eds, kw_dds"
        )
