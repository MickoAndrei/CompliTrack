<?php

return [
    'url' => rtrim(env('ONLYOFFICE_URL', ''), '/'),
    // Used only by HTTP requests made through a loopback CompliTrack hostname.
    'local_url' => rtrim(env('ONLYOFFICE_LOCAL_URL', 'http://127.0.0.1:8082'), '/'),
    'internal_url' => rtrim(env('ONLYOFFICE_INTERNAL_URL', 'http://127.0.0.1:8082'), '/'),
    'app_url' => rtrim(env('ONLYOFFICE_APP_URL', 'http://host.docker.internal:8000'), '/'),
    'secret' => env('ONLYOFFICE_JWT_SECRET', ''),
];
