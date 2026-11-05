<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime
    |--------------------------------------------------------------------------
    |
    | The one switch between the two builds (platform.md §3a): "server" for
    | the web build, "mobile" for the NativePHP build. Read it through
    | App\Support\Runtime. A missing value is "server", never "mobile".
    |
    */

    'runtime' => env('ITP_RUNTIME', 'server'),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    |
    | itp-server's API host, which both builds call for sign-in, profile
    | changes and generation. The version is sent as X-App-Version on every
    | call; the web build sends its deployed version.
    |
    */

    'api_base_url' => env('API_BASE_URL', 'https://api.itstotallypossible.life'),

    'version' => env('ITP_APP_VERSION', '0.1.0'),

];
