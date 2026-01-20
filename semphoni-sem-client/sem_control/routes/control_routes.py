"""
Mouse and keyboard control API routes.
"""
from flask import Blueprint, request, jsonify
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

