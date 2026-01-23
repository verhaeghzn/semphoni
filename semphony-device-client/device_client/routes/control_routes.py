"""
Mouse and keyboard control API routes.
"""
from flask import Blueprint, request, jsonify, current_app
import pyautogui
from ..auth import require_password

bp = Blueprint("control", __name__)


@bp.route("/move-click", methods=["POST"])
@require_password
def move_click():
    """Move mouse to coordinates and click."""
    data = request.json
    x = int(data["x"])
    y = int(data["y"])
    pyautogui.moveTo(x, y, duration=0.2)
    pyautogui.click()
    return jsonify({"status": "ok"})


@bp.route("/type-text", methods=["POST"])
@require_password
def type_text():
    """Type text at the current cursor position."""
    data = request.json
    text = data["text"]
    pyautogui.typewrite(text, interval=0.02)
    return jsonify({"status": "ok"})


@bp.route("/key-press", methods=["POST"])
@require_password
def key_press():
    """Press a keyboard key."""
    data = request.json
    key = data["key"]  # e.g. "enter"
    pyautogui.press(key)
    return jsonify({"status": "ok"})


@bp.route("/metrics", methods=["GET"])
@require_password
def metrics():
    """
    Return key hardware metrics (when hardware supports it).

    This mirrors the Reverb "get_metrics" server-command.
    """
    hardware_controller = current_app.config.get('hardware_controller')
    if not hardware_controller:
        return jsonify({"error": "Hardware controller not initialized"}), 500
    
    m = hardware_controller.get_metrics()
    status = 200 if m.get("supported", False) else 503
    return jsonify(m), status

