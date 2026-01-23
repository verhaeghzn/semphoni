"""
Authentication utilities for the device control server.
"""
from functools import wraps
from flask import request, abort
import os


# Get password from environment variable, default to "hello123"
PASSWORD = os.getenv("SERVER_PASSWORD", "hello123")


def require_password(f):
    """Decorator to require password authentication for a route."""
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
            abort(500, "Server password not configured")
        
        if provided_password != PASSWORD:
            abort(401, "Unauthorized: Invalid password")
        
        return f(*args, **kwargs)
    return decorated_function

