"""
Main entrypoint for SEM control client/server.

By default this process does NOT expose a REST API. Instead it connects as a
Reverb/Pusher-compatible WebSocket client and stays connected (with retries).

To expose the REST API locally, run with --rest (implemented in rest_main.py).
"""
"""
Compatibility entrypoint.

This repo's Python code lives in the `sem_control/` package now.
This file remains so existing commands like `python Server/main.py` keep working.
"""

from sem_control.main import main


if __name__ == "__main__":
    raise SystemExit(main())
