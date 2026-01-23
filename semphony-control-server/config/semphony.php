<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Semphony client compatibility
    |--------------------------------------------------------------------------
    |
    | The minimum/expected SEM Python client version for this server.
    | Set this in the server .env as PY_CLIENT_VERSION=1.0.0
    |
    */
    'py_client_version' => env('PY_CLIENT_VERSION', ''),
];

