"""
Screenshot capture and upload management.
"""

from __future__ import annotations

import io
import logging
import os
import time
from typing import Any, Dict, Optional

import mss
from PIL import Image

from .hardware_controller import HardwareController

logger = logging.getLogger(__name__)


def _json_log(event: str, **fields: Any) -> None:
    """Log JSON-formatted event."""
    import json
    payload = {"event": event, **fields}
    try:
        logger.info("%s", json.dumps(payload, separators=(",", ":"), sort_keys=True))
    except Exception:
        logger.info("event=%s fields=%r", event, fields)


def _http_post_multipart(
    url: str,
    headers: Dict[str, str],
    fields: Dict[str, str],
    files: Dict[str, tuple],
    insecure_ssl: bool = False,
) -> Dict[str, Any]:
    """POST multipart/form-data request."""
    import urllib.request
    import ssl
    from urllib.parse import urlparse

    boundary = "----WebKitFormBoundary" + "".join([str(i) for i in range(10)])
    body_parts = []

    # Add fields
    for key, value in fields.items():
        body_parts.append(f"--{boundary}\r\n".encode())
        body_parts.append(f'Content-Disposition: form-data; name="{key}"\r\n\r\n'.encode())
        body_parts.append(f"{value}\r\n".encode())

    # Add files
    for key, (filename, file_data, content_type) in files.items():
        body_parts.append(f"--{boundary}\r\n".encode())
        body_parts.append(
            f'Content-Disposition: form-data; name="{key}"; filename="{filename}"\r\n'.encode()
        )
        body_parts.append(f"Content-Type: {content_type}\r\n\r\n".encode())
        body_parts.append(file_data)
        body_parts.append("\r\n".encode())

    body_parts.append(f"--{boundary}--\r\n".encode())
    body = b"".join(body_parts)

    req = urllib.request.Request(url, data=body, headers=headers)
    req.add_header("Content-Type", f"multipart/form-data; boundary={boundary}")
    req.add_header("Content-Length", str(len(body)))

    ctx = None
    if insecure_ssl:
        p = urlparse(url)
        if p.scheme == "https":
            ctx = ssl.create_default_context()
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE

    with urllib.request.urlopen(req, context=ctx) as resp:
        import json
        return json.loads(resp.read().decode("utf-8"))


def _ddev_auth_url_candidates(url: str) -> list[str]:
    """Generate DDEV URL candidates for auth endpoints."""
    from urllib.parse import urlparse, urlunparse

    p = urlparse(url)
    if ".ddev.site" not in p.netloc and ":" not in p.netloc:
        return [url]

    candidates = []
    if ":" not in p.netloc:
        # Try common DDEV ports
        for port in [8080, 8443]:
            new_netloc = f"{p.netloc}:{port}"
            candidates.append(urlunparse((p.scheme, new_netloc, p.path, p.params, p.query, p.fragment)))
    else:
        candidates.append(url)

    return candidates


class ScreenshotManager:
    """Manages screenshot capture and upload."""

    def __init__(self, hardware_controller: HardwareController):
        """
        Initialize screenshot manager.

        Args:
            hardware_controller: Hardware controller for configuration access
        """
        self.hardware = hardware_controller

    def capture_and_upload(
        self,
        payload: Dict[str, Any],
        upload_url: str,
        client_key: str,
        insecure_ssl: bool = False,
    ) -> Dict[str, Any]:
        """
        Capture screenshot and upload to server.

        Args:
            payload: Command payload with optional monitor_nr, format, quality
            upload_url: URL to upload screenshot to
            client_key: Client authentication key
            insecure_ssl: Whether to skip SSL verification

        Returns:
            Dict with upload response including artifact_id, size, etc.

        Raises:
            RuntimeError: If screenshot upload fails
        """
        config = self.hardware.get_screenshot_config()
        monitor_nr = int(payload.get("monitor_nr") or config["monitor_number"])
        quality = int(payload.get("quality") or config["jpeg_quality"])
        quality = max(1, min(100, quality))

        fmt = str(payload.get("format") or "jpeg").strip().lower()
        if fmt not in {"jpeg", "jpg", "image/jpeg"}:
            raise ValueError("Only JPEG screenshots are supported")

        t0 = time.time()
        _json_log(
            "screenshot_start",
            monitor_nr=monitor_nr,
            format="jpeg",
            quality=quality,
        )

        # Capture screenshot
        with mss.mss() as sct:
            monitor = sct.monitors[monitor_nr]
            t_grab0 = time.time()
            sct_img = sct.grab(monitor)
            t_grab1 = time.time()

            # Convert to PIL Image and encode as JPEG
            img = Image.frombytes("RGB", sct_img.size, sct_img.rgb)
            buf = io.BytesIO()
            t_enc0 = time.time()
            img.save(buf, format="JPEG", quality=quality, optimize=True)
            t_enc1 = time.time()
            img_bytes = buf.getvalue()
            mime = "image/jpeg"

        # Upload screenshot
        upload_resp: Optional[Dict[str, Any]] = None
        last_upload_err: Optional[Exception] = None
        for candidate_url in _ddev_auth_url_candidates(upload_url):
            try:
                upload_resp = _http_post_multipart(
                    candidate_url,
                    headers={"X-Client-Key": client_key},
                    fields={"monitor_nr": str(monitor_nr)},
                    files={"image": ("latest.jpg", img_bytes, "image/jpeg")},
                    insecure_ssl=insecure_ssl,
                )
                break
            except Exception as e:
                last_upload_err = e
                continue

        if upload_resp is None:
            raise RuntimeError(f"Screenshot upload failed using {upload_url}") from last_upload_err

        t1 = time.time()
        _json_log(
            "screenshot_done",
            monitor_nr=monitor_nr,
            format="jpeg",
            quality=quality,
            bytes=len(img_bytes),
            width=int(sct_img.size.width),
            height=int(sct_img.size.height),
            grab_ms=round((t_grab1 - t_grab0) * 1000.0, 2),
            encode_ms=round((t_enc1 - t_enc0) * 1000.0, 2),
            total_ms=round((t1 - t0) * 1000.0, 2),
        )

        return {
            "artifact_id": upload_resp.get("id"),
            "mime": mime,
            "format": "jpeg",
            "quality": quality,
            "bytes": len(img_bytes),
            "monitor_nr": monitor_nr,
            "size": {"width": int(sct_img.size.width), "height": int(sct_img.size.height)},
        }
