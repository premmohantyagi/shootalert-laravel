<?php

namespace ShootAlert\Laravel;

use Illuminate\Http\Request;
use Monolog\Handler\AbstractProcessingHandler;
use ShootAlert\Laravel\Jobs\ShipErrorEventJob;
use Throwable;

/**
 * Monolog handler registered as Laravel's `shootalert` logging channel.
 * Customers stack it alongside their default channel so every uncaught
 * exception Laravel routes to the log also routes here. Plain log lines
 * without an exception are ignored — the SDK is for exception telemetry,
 * not log shipping.
 *
 * Works under both Monolog 2 (Laravel 8/9) and Monolog 3 (Laravel 10+):
 * the level constant 400 is ERROR in both, and write() takes an untyped
 * record so its signature stays compatible with Monolog 2's array record
 * and Monolog 3's LogRecord object.
 */
class Logger extends AbstractProcessingHandler
{
    public function __construct($level = 400, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * @param  array<string, mixed>|\Monolog\LogRecord  $record
     */
    protected function write($record): void
    {
        if (! config('shootalert.enabled', true)) {
            return;
        }

        // Monolog 2 hands us an array record; Monolog 3 a LogRecord object.
        $context = is_array($record) ? ($record['context'] ?? []) : $record->context;

        $exception = $context['exception'] ?? null;
        if (! $exception instanceof Throwable) {
            // Plain log lines (warning/info/notice) are recorded as breadcrumbs
            // by the service provider's event listener; we only ship actual
            // exceptions through this handler.
            return;
        }

        try {
            $request = $this->resolveRequest();
            $event = app(EventBuilder::class)->build($exception, $request);

            $job = ShipErrorEventJob::dispatch($event);

            if ($conn = config('shootalert.queue.connection')) {
                $job->onConnection($conn);
            }
            $job->onQueue(config('shootalert.queue.name', 'default'));
        } catch (Throwable $e) {
            // Last-ditch: never let SDK breakage crash the host app.
            error_log('shootalert: failed to dispatch error event: '.$e->getMessage());
        }
    }

    private function resolveRequest(): ?Request
    {
        try {
            $r = app('request');
            return $r instanceof Request ? $r : null;
        } catch (Throwable) {
            return null;
        }
    }
}
