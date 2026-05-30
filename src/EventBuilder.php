<?php

namespace ShootAlert\Laravel;

use Illuminate\Http\Request;
use Throwable;

/**
 * Translates a Throwable + current Request into the JSON shape the ShootAlert
 * /api/v1/errors/events endpoint accepts. Marks stack frames inside the
 * customer's app (anything under base_path() that isn't /vendor/) as
 * `in_project` so the platform's Fingerprinter and AI diagnosis prompt
 * can anchor on the customer's own code.
 */
class EventBuilder
{
    public function __construct(
        private BreadcrumbRecorder $breadcrumbs,
        private Redactor $redactor,
        private string $basePath,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Throwable $e, ?Request $request): array
    {
        return [
            'occurred_at' => date('c'),
            'exception_class' => get_class($e),
            'message' => $e->getMessage(),
            'stack_trace' => $this->buildStackTrace($e),
            'breadcrumbs' => $this->breadcrumbs->dump(),
            'request' => $this->buildRequest($request),
            'runtime' => $this->buildRuntime(),
            'user' => $this->buildUser($request),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildStackTrace(Throwable $e): array
    {
        $frames = [];

        // Frame 0 is the throw site itself, which isn't in getTrace().
        $frames[] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'function' => null,
            'class' => null,
            'in_project' => $this->isInProject($e->getFile()),
        ];

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            $frames[] = [
                'file' => $file,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'in_project' => $file !== null && $this->isInProject($file),
            ];
        }

        return $frames;
    }

    private function isInProject(?string $file): bool
    {
        if ($file === null || $this->basePath === '') {
            return false;
        }
        if (! str_starts_with($file, $this->basePath)) {
            return false;
        }
        return ! str_contains($file, '/vendor/') && ! str_contains($file, '\\vendor\\');
    }

    /** @return array<string, mixed>|null */
    private function buildRequest(?Request $request): ?array
    {
        if ($request === null) {
            return null;
        }

        return $this->redactor->redact([
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'route' => optional($request->route())->getName(),
            'user_agent' => substr((string) $request->userAgent(), 0, 240),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildRuntime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => class_exists(\Illuminate\Foundation\Application::class)
                ? \Illuminate\Foundation\Application::VERSION
                : 'unknown',
            'env' => app()->environment(),
            'sdk' => 'shootalert-laravel',
            'sdk_version' => '0.1.0',
        ];
    }

    /** @return array<string, mixed>|null */
    private function buildUser(?Request $request): ?array
    {
        if ($request === null) {
            return null;
        }

        try {
            $user = $request->user();
        } catch (Throwable) {
            return null;
        }

        if ($user === null) {
            return null;
        }

        $id = method_exists($user, 'getAuthIdentifier')
            ? (string) $user->getAuthIdentifier()
            : null;

        return $id === null ? null : ['id' => $id];
    }
}
