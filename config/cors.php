<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Frontend TapPay (mobile/web) memanggil API ini lewat Bearer token,
    | bukan cookie. Jadi origin boleh '*' dan supports_credentials = false.
    | Ini memastikan header CORS selalu terkirim, termasuk untuk preflight
    | (OPTIONS) dan respons error, supaya browser tidak memblokir fetch.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
