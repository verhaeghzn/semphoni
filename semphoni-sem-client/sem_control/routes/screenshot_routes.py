"""
Screenshot-related API routes.
"""
from flask import Blueprint, send_file
import pyautogui
import io
from ..auth import require_password

bp = Blueprint("screenshot", __name__)


@bp.route("/screenshot", methods=["GET"])
@require_password
def screenshot():
    """Capture and return a screenshot of the current screen."""
    img = pyautogui.screenshot()
    buf = io.BytesIO()
    img.save(buf, format="PNG")
    buf.seek(0)
    return send_file(buf, mimetype="image/png")

