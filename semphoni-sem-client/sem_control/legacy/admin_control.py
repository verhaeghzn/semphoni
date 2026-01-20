from flask import Flask, request, send_file, jsonify, abort, Response
import pyautogui
import io
from PIL import Image, ImageGrab
import os
from functools import wraps, partial
from dotenv import load_dotenv
import mss
import mss.tools

load_dotenv()

app = Flask(__name__)

ImageGrab.grab = partial(ImageGrab.grab, all_screens=True)

# Get password from environment variable
PASSWORD = os.getenv("SERVER_PASSWORD")
MONITOR_NR = int(os.getenv("SEMPC_MONITOR_NUMBER", 2))

def require_password(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Check for password in Authorization header or query parameter
        auth_header = request.headers.get("Authorization", "")
        password_param = request.args.get("password") or (request.json.get("password") if request.is_json else None)
        
        provided_password = None
        if auth_header.startswith("Bearer "):
            provided_password = auth_header.split(" ")[1]
        elif password_param:
            provided_password = password_param
        
        if not PASSWORD:
            abort(502, "Server password not configured")
        
        if provided_password != PASSWORD:
            abort(401, "Unauthorized: Invalid password")
        
        return f(*args, **kwargs)
    return decorated_function

@app.route("/screenshot", methods=["GET"])
def screenshot():
    #img = pyautogui.screenshot()
    with mss.mss() as sct:
        monitor = sct.monitors[MONITOR_NR]
        sct_img = sct.grab(monitor)
        png = mss.tools.to_png(sct_img.rgb, sct_img.size)
    return Response(png, mimetype="image/png")

@app.route("/move-click", methods=["POST"])
@require_password
def move_click():
    data = request.json
    x = int(data["x"])
    y = int(data["y"])
    pyautogui.moveTo(x, y, duration=0.2)
    pyautogui.click()
    return jsonify({"status": "ok"})

@app.route("/type-text", methods=["POST"])
@require_password
def type_text():
    data = request.json
    text = data["text"]
    pyautogui.typewrite(text, interval=0.02)
    return jsonify({"status": "ok"})

@app.route("/key-press", methods=["POST"])
@require_password
def key():
    data = request.json
    key = data["key"]  # e.g. "enter"
    pyautogui.press(key)
    return jsonify({"status": "ok"})

if __name__ == "__main__":
    # Bind to specific LAN IP, not 0.0.0.0, in a real lab setup
    app.run(host="127.0.0.1", port=5005)
