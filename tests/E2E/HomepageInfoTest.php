<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class HomepageInfoTest extends BaseE2ETestCase
{
    public function testHomepageExposesServiceMetadataAndSessions(): void
    {
        $sessionId = $this->uniqueSession('homepage');
        $this->request('POST', "/{$sessionId}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['ping' => 'pong'],
        ]);

        $response = $this->request('GET', '/');
        self::assertSame(200, $response->getStatusCode());

        /**
         * @var array{
         *     service?: string,
         *     version?: string,
         *     endpoints?: array<string, string>,
         *     sessions?: array<int, array<string, mixed>>
         * } $payload
         */
        $payload = $this->decodeJson($response);

        self::assertSame('Webhook Receiver', $payload['service'] ?? null);
        self::assertSame('1.0.0', $payload['version'] ?? null);

        self::assertArrayHasKey('endpoints', $payload);
        self::assertArrayHasKey('sessions', $payload);

        /** @var array<string, string> $endpoints */
        $endpoints = $payload['endpoints'];
        self::assertSame('Capture webhook', $endpoints['POST /{session_id}'] ?? null);
        self::assertSame('Retrieve webhooks', $endpoints['GET /api/webhooks/{session_id}'] ?? null);
        self::assertSame('Clear webhooks', $endpoints['DELETE /api/webhooks/{session_id}'] ?? null);
        self::assertSame('List sessions', $endpoints['GET /api/webhooks'] ?? null);

        /** @var list<array<string, mixed>> $sessions */
        $sessions = array_values($payload['sessions']);
        $matched = array_filter(
            $sessions,
            static fn (array $session): bool => ($session['session_id'] ?? null) === $sessionId
        );

        self::assertNotEmpty($matched, 'Homepage did not report newly created session.');

        foreach ($sessions as $session) {
            if ($session['session_id'] === $sessionId) {
                self::assertSame(1, $session['webhook_count'] ?? null);
                self::assertArrayHasKey('last_webhook', $session);
            }
        }
    }

    public function testHomepageResponseStructure(): void
    {
        $response = $this->request('GET', '/');
        /** @var array<string, mixed> $payload */
        $payload = $this->decodeJson($response);

        self::assertArrayHasKey('service', $payload);
        self::assertArrayHasKey('version', $payload);
        self::assertArrayHasKey('endpoints', $payload);
        self::assertArrayHasKey('sessions', $payload);

        self::assertIsString($payload['service']);
        self::assertIsString($payload['version']);
        self::assertIsArray($payload['endpoints']);
        self::assertIsArray($payload['sessions']);
    }
}
