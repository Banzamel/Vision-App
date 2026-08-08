<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'channel' => env('BROADCAST_CHANNEL', 'vision'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                // Server-to-server address the queue worker uses to PUSH events into
                // Reverb. It is not the address the browser uses: the worker reaches the
                // Reverb process directly (loopback, plain HTTP, internal port), while
                // REVERB_HOST/PORT/SCHEME describe the public endpoint behind nginx.
                // Without the BROADCAST_* overrides the publisher would POST to
                // https://<public host>:443/apps/{id}/events — that lands in nginx, which
                // answers 404, and every broadcast ends up in failed_jobs.
                //
                // Never give Reverb a path prefix (REVERB_SERVER_PATH) to make this work:
                // the Pusher client signs the request with the UNPREFIXED path while
                // Reverb verifies the signature against the full incoming path, so a
                // prefixed server rejects every published event with a 401. The public
                // /ws prefix is stripped by nginx instead (proxy_pass with trailing slash).
                'host' => env('REVERB_BROADCAST_HOST', env('REVERB_HOST')),
                'port' => env('REVERB_BROADCAST_PORT', env('REVERB_PORT', 443)),
                'scheme' => env('REVERB_BROADCAST_SCHEME', env('REVERB_SCHEME', 'https')),
                'useTLS' => env('REVERB_BROADCAST_SCHEME', env('REVERB_SCHEME', 'https')) === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];

