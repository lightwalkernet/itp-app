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

    /*
    |--------------------------------------------------------------------------
    | Trusted browsers
    |--------------------------------------------------------------------------
    |
    | How long the web build stays signed in, from sign-in, when the user
    | ticks "This is my own device". The phone stays signed in until sign-out.
    |
    */

    'trusted_device_days' => (int) env('ITP_TRUSTED_DEVICE_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Where universal audio and images live: R2 behind a public custom domain.
    | Rows hold object keys (e.g. media/audio/universals/...); a key is
    | fetched from this host unless the app bundles the file in public/.
    |
    */

    'media_url' => env('ITP_MEDIA_URL', 'https://itp.numinous.fun'),

    /*
    |--------------------------------------------------------------------------
    | Content bundle
    |--------------------------------------------------------------------------
    |
    | The universal ITPs and wildcards exported by itp-server, shipped with
    | the mobile build so they show offline and before sign-in. The phone
    | applies it whenever the file changes (App\Content\ContentBundle).
    |
    */

    'content_bundle' => resource_path('content/snapshot.json'),

];
