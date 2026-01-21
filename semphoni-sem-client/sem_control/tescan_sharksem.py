"""
Minimal client for TESCAN SharkSEM remote control protocol.

This is intended for **read-only** telemetry (metrics), not image streaming.

Protocol basics (as seen in TESCAN's Python examples):
- TCP control channel on (host, port)
- TCP data channel on (host, port+1), registered via TcpRegDataPort(local_port)

We keep the data socket connected because many servers expect it, even if we
don't actively read image data.
"""

from __future__ import annotations

import socket
import struct
from dataclasses import dataclass
from typing import Iterable, List, Optional, Sequence, Tuple


class SharkSemError(RuntimeError):
    pass


def _pad4(n: int) -> int:
    return ((n + 3) // 4) * 4


def _encode_fn_name(fn_name: str) -> bytes:
    # The protocol uses a fixed 16-byte function name field padded with NULs.
    b = fn_name.encode("ascii", errors="replace")
    if len(b) > 16:
        b = b[:16]
    return b.ljust(16, b"\x00")


def _recv_fully(sock: socket.socket, size: int) -> bytes:
    chunks: List[bytes] = []
    remaining = size
    while remaining > 0:
        part = sock.recv(remaining)
        if not part:
            raise SharkSemError("Socket closed while receiving data")
        chunks.append(part)
        remaining -= len(part)
    return b"".join(chunks)


def _pack_int(value: int) -> bytes:
    return struct.pack("<i", int(value))


def _pack_uint(value: int) -> bytes:
    return struct.pack("<I", int(value))


def _pack_float(value: float) -> bytes:
    # SharkSEM represents floats as length-prefixed padded ASCII strings.
    s = str(float(value)).encode("ascii", errors="replace")
    l = _pad4(len(s))
    s = s.ljust(l, b"\x00")
    return struct.pack("<I", l) + s


def _unpack_int(body: bytes, offset: int) -> Tuple[int, int]:
    (v,) = struct.unpack_from("<i", body, offset)
    return v, offset + 4


def _unpack_uint(body: bytes, offset: int) -> Tuple[int, int]:
    (v,) = struct.unpack_from("<I", body, offset)
    return v, offset + 4


def _unpack_float(body: bytes, offset: int) -> Tuple[float, int]:
    (l,) = struct.unpack_from("<I", body, offset)
    offset += 4
    raw = body[offset : offset + l]
    offset += l
    # Trim at first NUL.
    raw = raw.split(b"\x00", 1)[0]
    try:
        return float(raw.decode("ascii", errors="replace")), offset
    except Exception as e:
        raise SharkSemError(f"Failed to parse float from {raw!r}") from e


Arg = Tuple[str, object]
RetType = str  # "int" | "uint" | "float"


@dataclass
class SharkSemClient:
    host: str
    port: int
    timeout_s: float = 2.0

    _sock_c: Optional[socket.socket] = None
    _sock_d: Optional[socket.socket] = None

    def connect(self) -> None:
        self.close()
        try:
            sock_c = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock_c.settimeout(self.timeout_s)
            sock_c.connect((self.host, self.port))

            # Data socket is required by many servers; register the local port first.
            sock_d = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock_d.settimeout(self.timeout_s)
            sock_d.bind(("", 0))
            local_port = int(sock_d.getsockname()[1])

            # Register local data port over control channel, then connect to port+1.
            self._sock_c = sock_c
            self.recv("TcpRegDataPort", ["int"], args=[("int", local_port)])
            sock_d.connect((self.host, self.port + 1))

            self._sock_d = sock_d
        except Exception as e:
            self.close()
            raise SharkSemError(f"Failed to connect to SharkSEM at {self.host}:{self.port}") from e

    def close(self) -> None:
        for s in (self._sock_c, self._sock_d):
            try:
                if s is not None:
                    s.close()
            except Exception:
                pass
        self._sock_c = None
        self._sock_d = None

    def is_connected(self) -> bool:
        return self._sock_c is not None and self._sock_d is not None

    def _ensure_connected(self) -> socket.socket:
        if self._sock_c is None:
            raise SharkSemError("Not connected")
        return self._sock_c

    def send(self, fn_name: str, *, args: Sequence[Arg] = ()) -> None:
        sock = self._ensure_connected()

        body = b""
        for t, v in args:
            if t == "int":
                body += _pack_int(int(v))  # type: ignore[arg-type]
            elif t == "uint":
                body += _pack_uint(int(v))  # type: ignore[arg-type]
            elif t == "float":
                body += _pack_float(float(v))  # type: ignore[arg-type]
            else:
                raise SharkSemError(f"Unsupported arg type: {t}")

        # Header format: <IIHHI> (body_size, id, flags, queue, reserved)
        # We keep all flags at 0 (no waits).
        hdr = _encode_fn_name(fn_name) + struct.pack("<IIHHI", len(body), 0, 0, 0, 0)
        sock.sendall(hdr + body)

    def recv(self, fn_name: str, ret: Sequence[RetType], *, args: Sequence[Arg] = ()) -> List[object]:
        sock = self._ensure_connected()
        self.send(fn_name, args=args)

        fn_recv = _recv_fully(sock, 16)
        _hdr = _recv_fully(sock, 16)
        (body_size, _id, _flags, _queue, _reserved) = struct.unpack("<IIHHI", _hdr)
        body = _recv_fully(sock, body_size)

        # Some servers may send unrelated messages; reject mismatched responses.
        # The examples assume request/response ordering; we keep it simple here.
        if fn_recv.split(b"\x00", 1)[0] != _encode_fn_name(fn_name).split(b"\x00", 1)[0]:
            raise SharkSemError(f"Unexpected response function name: {fn_recv!r} (expected {fn_name!r})")

        out: List[object] = []
        offset = 0
        for t in ret:
            if t == "int":
                v, offset = _unpack_int(body, offset)
                out.append(v)
            elif t == "uint":
                v, offset = _unpack_uint(body, offset)
                out.append(v)
            elif t == "float":
                v, offset = _unpack_float(body, offset)
                out.append(v)
            else:
                raise SharkSemError(f"Unsupported return type: {t}")
        return out

    def recv_int(self, fn_name: str, *, args: Sequence[Arg] = ()) -> int:
        return int(self.recv(fn_name, ["int"], args=args)[0])

    def recv_float(self, fn_name: str, *, args: Sequence[Arg] = ()) -> float:
        return float(self.recv(fn_name, ["float"], args=args)[0])

