<?php

namespace ShootAlert\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ShootAlert\Laravel\ApiClient;

/**
 * Pushes one error event over HTTPS, off the request thread. The job is
 * defensive: it never re-throws, since a failure here means "ShootAlert is
 * unreachable" and that must not break the customer's app. Retries cover
 * transient network blips; permanent failure logs and moves on.
 */
class ShipErrorEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /** @param  array<string, mixed>  $event */
    public function __construct(public readonly array $event) {}

    public function handle(ApiClient $client): void
    {
        $result = $client->ship($this->event);

        if (! $result['ok']) {
            // Log via stderr-equivalent; never crash the queue worker.
            error_log(sprintf(
                'shootalert: ingest failed (status=%d body=%s)',
                $result['status'],
                substr($result['body'], 0, 240),
            ));
        }
    }
}
