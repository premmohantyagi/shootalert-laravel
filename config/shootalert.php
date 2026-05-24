<?php

return [
    /*
     * Per-app secret from the ShootAlert UI. The SDK signs every request
     * with this token via HMAC-SHA256; the server uses it to look up the
     * destination ErrorApp.
     */
    'key' => env('SHOOTALERT_ERROR_KEY'),

    /*
     * Ingest endpoint. Defaults to the public ShootAlert instance; override
     * to point at self-hosted or staging deployments.
     */
    'endpoint' => env('SHOOTALERT_ERROR_ENDPOINT', 'https://shootalert.com/api/v1/errors/events'),

    /*
     * Master switch. Useful during local dev so you don't pollute the live
     * dashboard with noisy stack traces from your laptop.
     */
    'enabled' => env('SHOOTALERT_ENABLED', true),

    /*
     * Queue connection + name the ShipErrorEventJob is dispatched onto.
     * Errors ship asynchronously so the originating request returns
     * without waiting on an outbound HTTPS call.
     */
    'queue' => [
        'connection' => env('SHOOTALERT_QUEUE_CONNECTION'),
        'name' => env('SHOOTALERT_QUEUE_NAME', 'default'),
    ],

    'breadcrumbs' => [
        'max' => 25,
        'capture_queries' => true,
        'capture_logs' => true,
    ],

    /*
     * Substring matches stripped from outgoing payloads. Case-insensitive.
     * Anything whose key contains one of these gets replaced with [redacted].
     */
    'redact_keys' => [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'authorization', 'auth', 'cookie',
    ],
];
