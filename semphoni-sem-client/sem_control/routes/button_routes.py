"""
Button-related API routes.
"""
from flask import Blueprint, request, jsonify
import pyautogui
import time
from ..auth import require_password
from ..utils import validate_button, get_button_coordinates, ButtonValidationError

bp = Blueprint("buttons", __name__)


@bp.route("/gotoButton", methods=["POST"])
def goto_button():
    """Move the mouse to the center of a specified button."""
    try:
        # Check if request has JSON content
        if not request.is_json:
            return jsonify({"error": "Request must have Content-Type: application/json"}), 400
        
        data = request.json
        if data is None:
            return jsonify({"error": "Request body is empty or invalid JSON"}), 400
        
        if "button_name" not in data:
            return jsonify({"error": "Missing 'button_name' in request body"}), 400

        button_name = data["button_name"]
        if not button_name or not isinstance(button_name, str):
            return jsonify({"error": "button_name must be a non-empty string"}), 400
        
        try:
            button_info, center = validate_button(button_name)
        except ButtonValidationError as e:
            return jsonify({"error": e.message}), e.status_code
        
        duration = data.get("duration", 0.3)
        pyautogui.moveTo(center["x"], center["y"], duration=duration)
        
        return jsonify({
            "status": "ok",
            "button_name": button_name,
            "position": center
        })
    except Exception as e:
        return jsonify({"error": f"Internal server error: {str(e)}"}), 500


@bp.route("/clickButton", methods=["POST"])
@require_password
def click_button():
    """Move the mouse to and click a specified button."""
    try:
        # Check if request has JSON content
        if not request.is_json:
            return jsonify({"error": "Request must have Content-Type: application/json"}), 400
        
        data = request.json
        if data is None:
            return jsonify({"error": "Request body is empty or invalid JSON"}), 400
        
        if "button_name" not in data:
            return jsonify({"error": "Missing 'button_name' in request body"}), 400
        
        button_name = data["button_name"]
        if not button_name or not isinstance(button_name, str):
            return jsonify({"error": "button_name must be a non-empty string"}), 400
        
        try:
            button_info, center = validate_button(button_name)
        except ButtonValidationError as e:
            return jsonify({"error": e.message}), e.status_code
        
        duration = data.get("duration", 0.3)
        clicks = data.get("clicks", 1)
        interval = data.get("interval", 0.1)
        button = data.get("button", "left")  # 'left', 'right', or 'middle'
        confirmation_wait = data.get("confirmation_wait", 1.0)  # Wait time before confirmation
        
        pyautogui.moveTo(center["x"], center["y"], duration=duration)
        pyautogui.click(center["x"], center["y"], clicks=clicks, interval=interval, button=button)
        
        # Check if button requires confirmation
        requires_confirmation = button_info.get("requires_confirmation", False)
        confirmed = False
        if requires_confirmation:
            time.sleep(confirmation_wait)
            pyautogui.press("enter")
            confirmed = True
        
        response = {
            "status": "ok",
            "button_name": button_name,
            "position": center,
            "clicks": clicks,
            "button": button
        }
        if confirmed:
            response["confirmed"] = True
            response["confirmation_wait"] = confirmation_wait
        
        return jsonify(response)
    except Exception as e:
        return jsonify({"error": f"Internal server error: {str(e)}"}), 500

