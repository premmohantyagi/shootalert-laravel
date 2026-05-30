<?php

namespace ShootAlert\Laravel;

/**
 * Strips secrets from outgoing payloads. Walks any nested array and
 * replaces values whose KEY matches a configured needle (case-insensitive
 * substring) with '[redacted]'. Scalar values aren't pattern-matched —
 * we'd rather leak a credit card number once than mis-redact a real
 * error message and lose all signal. The platform's Redactor does the
 * pattern-based pass.
 */
class Redactor
{
    /** @param  list<string>  $needles */
    public function __construct(private array $needles = []) {}

    public function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $this->shouldRedact((string) $k) ? '[redacted]' : $this->redact($v);
        }

        return $out;
    }

    private function shouldRedact(string $key): bool
    {
        $lower = strtolower($key);
        foreach ($this->needles as $needle) {
            if ($needle !== '' && str_contains($lower, strtolower($needle))) {
                return true;
            }
        }
        return false;
    }
}
