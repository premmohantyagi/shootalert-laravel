<?php

namespace ShootAlert\Laravel;

use Illuminate\Http\Request;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use ShootAlert\Laravel\Jobs\ShipErrorEventJob;
use Throwable;

/**
 * Monolog handler registered as Laravel's `shootalert` logging channel.
 * Customers stack it alongside their default channel so every uncaught
 * exception Laravel routes to the log also routes here. Plain log lines
 * without an exception are ignored — the SDK is for exception telemetry,
 * not log shipping.
 */
class Logger extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (! config('shootalert.enabled', true)) {
            return;
        }

        $exception = $record->context['exception'] ?? null;
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
