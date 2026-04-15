<?php

return [
    'env'           => env('BIOPAGO_ENV', 'quality'),
    'base_url'      => env('BIOPAGO_BASE_URL'),
    'token_url'     => env('BIOPAGO_TOKEN_URL'),
    'client_id'     => env('BIOPAGO_CLIENT_ID'),
    'client_secret' => env('BIOPAGO_CLIENT_SECRET'),
    'url_to_return' => env('BIOPAGO_RETURN_URL'),
    'frontend_url'  => env('FRONTEND_URL'),
];
