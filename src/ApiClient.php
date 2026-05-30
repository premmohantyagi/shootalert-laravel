<?php

namespace ShootAlert\Laravel;

use Illuminate\Support\Facades\Http;

/**
 * Wraps the HMAC-signed POST to /api/v1/errors/events. The request body is
 * pre-serialised so the signature covers the exact bytes the platform
 * verifies — Laravel's automatic array-to-JSON serialisation can re-order
 * keys, which would invalidate the signature.
 */
class ApiClient
{
    public function __construct(
        private string $endpoint,
        private string $token,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     * @return array{ok: bool, status: int, body: string}
     */
    public function ship(array $event): array
    {
        $body = (string) json_encode(['events' => [$event]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.$body, $this->token);

        try {
            // No `throw:` named arg here — that retry() parameter only exists on
            // Laravel 9+. The try/catch gives the same "never throw, report a
            // failed result" behaviour across Laravel 8–13.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Sa-Project-Token' => $this->token,
                'X-Sa-Timestamp' => $timestamp,
                'X-Sa-Signature' => $signature,
            ])
                ->withBody($body, 'application/json')
                ->timeout(10)
                ->retry(2, 250)
                ->post($this->endpoint);
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'body' => $e->getMessage()];
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => (string) $response->body(),
        ];
    }
}
