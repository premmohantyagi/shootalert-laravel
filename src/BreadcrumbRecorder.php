<?php

namespace ShootAlert\Laravel;

/**
 * Per-request ring buffer. The Laravel container resolves this as a
 * singleton, which in HTTP requests means "lives for one request's
 * lifecycle" — exactly the right scope for breadcrumbs leading up to
 * an exception.
 */
class BreadcrumbRecorder
{
    /** @var list<array{type: string, timestamp: string, message: string}> */
    private array $entries = [];

    public function __construct(private int $max = 25) {}

    public function record(string $type, string $message): void
    {
        $this->entries[] = [
            'type' => $type,
            'timestamp' => date('c'),
            'message' => $message,
        ];

        if (count($this->entries) > $this->max) {
            // Drop the oldest entry. Keeping the array re-indexed avoids a
            // sparse JSON array on the wire.
            $this->entries = array_values(array_slice($this->entries, -$this->max));
        }
    }

    /** @return list<array{type: string, timestamp: string, message: string}> */
    public function dump(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
