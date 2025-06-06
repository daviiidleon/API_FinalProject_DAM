<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines which domains are allowed to access your
    | application's resources from a different domain. You may enable
    | access for everyone or restrict access to specific domains.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:8100'], // ¡ESTE ES EL CAMBIO CLAVE!
    // Si tu app Ionic Angular usa otro puerto (ej. 4200), cámbialo aquí.
    // Si necesitas más de uno, puedes ponerlos así: ['http://localhost:8100', 'http://localhost:4200']


    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permite todos los headers (incluyendo Authorization)

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false, // Deja esto en 'false' para Sanctum API tokens.

];