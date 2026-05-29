# ShootAlert Laravel SDK

Ship uncaught exceptions from your Laravel app to ShootAlert with stack traces, breadcrumbs (recent log lines + DB queries), request context, and authenticated user info.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13

## Install

```bash
composer require shootalert/shootalert-laravel
```

Add to your `.env`:

```
SHOOTALERT_ERROR_KEY=your-app-token-from-the-shootalert-ui
SHOOTALERT_ERROR_ENDPOINT=https://shootalert.com/api/v1/errors/events
```

Wire the channel into your default logging stack in `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'shootalert'],   // <-- add 'shootalert'
        'ignore_exceptions' => false,
    ],

    'shootalert' => [
        'driver' => 'monolog',
        'handler' => \ShootAlert\Laravel\Logger::class,
        'level' => 'error',
    ],
],
```

Done. Any uncaught exception Laravel logs will be shipped to ShootAlert asynchronously via your queue.

## Configure (optional)

Publish the config file to override defaults:

```bash
php artisan vendor:publish --tag=shootalert-config
```

The published file at `config/shootalert.php` lets you:

- Toggle the SDK on/off (`SHOOTALERT_ENABLED=false`)
- Choose a specific queue connection/name for the ship job
- Tune the breadcrumb buffer size or disable query/log capture
- Extend the list of key substrings stripped from outgoing payloads

## What gets captured

- **Exception class, message, top-of-stack file:line**
- **Full stack trace**, with frames inside your app marked `in_project` for grouping and AI diagnosis anchoring
- **Breadcrumbs** (ring buffer of 25): every `info`/`warning`/`notice` log line + every DB query the request fired before the throw
- **Request context**: URL, method, IP, route name, user agent
- **Authenticated user ID** (if any) — never the email or anything else from the user model
- **Runtime info**: PHP version, Laravel version, app environment, SDK version

## What gets redacted before send

Any array key matching `password`, `secret`, `token`, `api_key`, `authorization`, `auth`, or `cookie` (case-insensitive substring) is replaced with `[redacted]` in the request context before the payload leaves the process. Add your own substrings via `shootalert.redact_keys`.

The platform applies a second pass of regex-based redaction (emails, IPv4 addresses, bearer tokens, hex tokens) on the receiving side.

## How it ships

Each exception becomes a queued job (`ShipErrorEventJob`) so the HTTP response that triggered the error returns immediately. The job POSTs one HMAC-signed request to ShootAlert with up to two retries on transient failure. If the platform is permanently unreachable, the job logs to stderr and gives up — your app is never broken by SDK failures.

## Disabling for local dev

In `.env.local` or wherever you keep dev settings:

```
SHOOTALERT_ENABLED=false
```

That short-circuits the Monolog handler before any work is done.
