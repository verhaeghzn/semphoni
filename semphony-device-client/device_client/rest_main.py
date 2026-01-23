"""
Opt-in REST API (Flask) server for device control.

This file intentionally exists separate from main.py so the default entrypoint
does not expose a REST API unless explicitly requested via --rest.
"""

from __future__ import annotations

import json
import logging

from dotenv import load_dotenv
from flask import Flask, jsonify, request

logger = logging.getLogger(__name__)

from .routes.button_routes import bp as button_bp
from .routes.control_routes import bp as control_bp
from .routes.screenshot_routes import bp as screenshot_bp
from .hardware import create_hardware_controller

load_dotenv()


def create_app() -> Flask:
    app = Flask(__name__)
    
    # Create hardware controller and store in app context
    try:
        hardware_controller = create_hardware_controller()
        hardware_controller.initialize()
        app.config['hardware_controller'] = hardware_controller
        logger.info("Hardware controller initialized: %s", hardware_controller.hardware_name)
    except Exception as e:
        logger.error("Failed to initialize hardware controller: %s", e)
        raise

    @app.errorhandler(400)
    def handle_bad_request(e):
        """Handle 400 errors and return JSON responses."""
        if request.is_json or request.content_type == "application/json":
            try:
                request.get_json()
            except Exception:
                return jsonify({"error": "Invalid JSON in request body"}), 400
        return (
            jsonify(
                {
                    "error": str(e.description)
                    if hasattr(e, "description")
                    else "Bad request"
                }
            ),
            400,
        )

    @app.before_request
    def log_request_body():
        """Log the request body for all requests."""
        if request.method in ["POST", "PUT", "PATCH"]:
            try:
                if request.is_json:
                    try:
                        body = (
                            json.dumps(request.json, indent=2)
                            if request.json
                            else "null"
                        )
                        logging.getLogger(__name__).info(
                            f"Request body ({request.method} {request.path}):\n{body}"
                        )
                    except Exception:
                        body = (
                            request.data.decode("utf-8", errors="replace")
                            if request.data
                            else "empty"
                        )
                        logging.getLogger(__name__).info(
                            f"Request body ({request.method} {request.path}) [raw]:\n{body}"
                        )
                elif request.data:
                    body = request.data.decode("utf-8", errors="replace")
                    logging.getLogger(__name__).info(
                        f"Request body ({request.method} {request.path}):\n{body}"
                    )
                elif request.form:
                    body = dict(request.form)
                    logging.getLogger(__name__).info(
                        f"Request body ({request.method} {request.path}):\n{json.dumps(body, indent=2)}"
                    )
            except Exception as e:
                logging.getLogger(__name__).warning(f"Failed to log request body: {e}")

    # Register blueprints
    app.register_blueprint(button_bp)
    app.register_blueprint(screenshot_bp)
    app.register_blueprint(control_bp)

    return app


def run_rest_server(host: str = "127.0.0.1", port: int = 5005) -> None:
    # Configure logging here too since this can be invoked directly.
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s - %(levelname)s - %(message)s",
    )
    app = create_app()
    app.run(host=host, port=port)


if __name__ == "__main__":
    run_rest_server()

