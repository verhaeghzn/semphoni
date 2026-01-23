"""
PC1 simulator for the LAN relay.

Usage (on PC1 or your dev machine):
  LOCAL_RELAY_TOKEN=... python Server/pc1_simulator.py --host <pc2-ip>

It will:
- connect to ws://<host>:<port>
- authenticate via X-PC1-Token
- send a ping + status request
- then print any incoming cloud-routed messages (server-command) delivered by PC2.
"""

from __future__ import annotations

import argparse
import asyncio
import json
import os
from typing import Any, Dict


async def _run(host: str, port: int, token: str, client_id: str, max_message_bytes: int) -> None:
    import websockets  # type: ignore

    uri = f"ws://{host}:{port}"
    headers: Dict[str, str] = {"X-PC1-Token": token}
    if client_id:
        headers["X-Relay-Client-Id"] = client_id

    async with websockets.connect(
        uri,
        extra_headers=headers,
        ping_interval=None,
        max_size=max_message_bytes,
    ) as ws:
        try:
            welcome = await ws.recv()
            print(welcome)
        except Exception:
            pass

        await ws.send(json.dumps({"type": "ping", "msg_id": "1"}))
        print(await ws.recv())

        await ws.send(json.dumps({"type": "status"}))
        print(await ws.recv())

        print("Listening for messages. Ctrl+C to exit.")
        async for raw in ws:
            try:
                obj: Any = json.loads(raw) if isinstance(raw, str) else raw
            except Exception:
                obj = raw
            print(obj)


def main() -> None:
    p = argparse.ArgumentParser(description="PC1 simulator for PC2 local relay")
    p.add_argument("--host", default=os.getenv("LOCAL_RELAY_HOST", "127.0.0.1"))
    p.add_argument("--port", type=int, default=int(os.getenv("LOCAL_RELAY_PORT", "8765")))
    p.add_argument("--token", default=os.getenv("LOCAL_RELAY_TOKEN", ""))
    p.add_argument("--client-id", default=os.getenv("RELAY_CLIENT_ID", "pc1"))
    p.add_argument("--max-message-bytes", type=int, default=int(os.getenv("LOCAL_RELAY_MAX_MESSAGE_BYTES", str(1 * 1024 * 1024))))
    args = p.parse_args()

    if not args.token:
        raise SystemExit("Missing token. Set LOCAL_RELAY_TOKEN or pass --token.")

    asyncio.run(_run(args.host, args.port, args.token, args.client_id, args.max_message_bytes))


if __name__ == "__main__":
    main()

