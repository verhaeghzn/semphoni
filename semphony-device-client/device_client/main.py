"""
Main entrypoint for device control client/server.

By default this process does NOT expose a REST API. Instead it connects as a
Reverb/Pusher-compatible WebSocket client and stays connected (with retries).

To expose the REST API locally, run with --rest (implemented in rest_main.py).
"""

from __future__ import annotations

import argparse
import logging
import sys
from typing import Sequence

from dotenv import load_dotenv

from .reverb_client import ReverbClientConfig, run_reverb_client_forever, warn_if_client_version_mismatch

load_dotenv()

logger = logging.getLogger(__name__)


def print_banner() -> None:
    """Print the ASCII art banner."""
    print("\n" + "   " + "=" * 60)
    print(
        """   ███████╗███████╗███╗   ███╗██████╗ ██╗  ██╗ ██████╗ ███╗   ██╗██╗
   ██╔════╝██╔════╝████╗ ████║██╔══██╗██║  ██║██╔═══██╗████╗  ██║██║
   ███████╗█████╗  ██╔████╔██║██████╔╝███████║██║   ██║██╔██╗ ██║██║
   ╚════██║██╔══╝  ██║╚██╔╝██║██╔═══╝ ██╔══██║██║   ██║██║╚██╗██║██║
   ███████║███████╗██║ ╚═╝ ██║██║     ██║  ██║╚██████╔╝██║ ╚████║██║
   ╚══════╝╚══════╝╚═╝     ╚═╝╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚═╝"""
    )
    print("   " + "=" * 60 + "\n")


def main(argv: Sequence[str] | None = None) -> int:
    # Ensure logging is configured even if invoked via `python -m device_client`.
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s - %(levelname)s - %(message)s",
    )

    parser = argparse.ArgumentParser(description="Device Control Server")
    parser.add_argument(
        "--rest",
        action="store_true",
        help="Expose local REST API (Flask) on 127.0.0.1:5005",
    )
    parser.add_argument(
        "--validate",
        action="store_true",
        help="Run button validation mode (interactive button selection)",
    )
    parser.add_argument(
        "--calibrate",
        action="store_true",
        help="Run button calibration mode (guided calibration of all buttons)",
    )
    args = parser.parse_args(list(argv) if argv is not None else None)

    print_banner()

    if args.validate:
        from .validate_buttons import validation_mode

        validation_mode()
        return 0

    if args.calibrate:
        from .validate_buttons import calibration_mode

        calibration_mode()
        return 0

    if args.rest:
        # Best-effort version check (only if cloud config is present).
        try:
            warn_if_client_version_mismatch(ReverbClientConfig.from_env())
        except Exception:
            pass

        from .rest_main import run_rest_server

        run_rest_server()
        return 0

    # Best-effort version check before connecting to cloud Reverb.
    try:
        warn_if_client_version_mismatch(ReverbClientConfig.from_env())
    except Exception:
        pass

    run_reverb_client_forever()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

