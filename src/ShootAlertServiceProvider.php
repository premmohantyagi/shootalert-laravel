<?php

namespace ShootAlert\Laravel;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ShootAlertServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shootalert.php', 'shootalert');

        $this->app->singleton(BreadcrumbRecorder::class, fn ($app) => new BreadcrumbRecorder(
            max: (int) config('shootalert.breadcrumbs.max', 25),
        ));

        $this->app->singleton(Redactor::class, fn ($app) => new Redactor(
            needles: (array) config('shootalert.redact_keys', []),
        ));

        $this->app->singleton(ApiClient::class, fn ($app) => new ApiClient(
            endpoint: (string) config('shootalert.endpoint'),
            token: (string) config('shootalert.key'),
        ));

        $this->app->bind(EventBuilder::class, fn ($app) => new EventBuilder(
            breadcrumbs: $app->make(BreadcrumbRecorder::class),
            redactor: $app->make(Redactor::class),
            basePath: $app->basePath(),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/shootalert.php' => config_path('shootalert.php'),
        ], 'shootalert-config');

        if (! config('shootalert.enabled', true)) {
            return;
        }

        $this->wireBreadcrumbListeners();
    }

    private function wireBreadcrumbListeners(): void
    {
        $recorder = $this->app->make(BreadcrumbRecorder::class);

        if (config('shootalert.breadcrumbs.capture_queries', true)) {
            DB::listen(function ($query) use ($recorder) {
                $sql = (string) $query->sql;
                if (strlen($sql) > 400) {
                    $sql = substr($sql, 0, 400).'…';
                }
                $recorder->record('query', sprintf('%s [%.1fms]', $sql, (float) $query->time));
            });
        }

        if (config('shootalert.breadcrumbs.capture_logs', true)) {
            Event::listen(MessageLogged::class, function (MessageLogged $event) use ($recorder) {
                // Skip error+ levels — the exception they describe is shipped
                // by the Monolog handler; double-recording would be noise.
                if (in_array(strtolower((string) $event->level), ['error', 'critical', 'alert', 'emergency'], true)) {
                    return;
                }
                $recorder->record((string) $event->level, (string) $event->message);
            });
        }
    }
}
