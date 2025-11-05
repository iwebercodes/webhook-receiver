<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class WebhookCaptureTest extends BaseE2ETestCase
{
    public function testCaptureAndRetrieveWebhook(): void
    {
        $sessionId = $this->uniqueSession('e2e-basic');
        $signature = 'sha256=' . bin2hex(random_bytes(8));
        $payload = [
            'event' => 'order.created',
            'data' => [
                'id' => 'order-' . bin2hex(random_bytes(4)),
                'amount' => 1399,
            ],
        ];

        $createResponse = $this->request('POST', "/{$sessionId}", [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'X-Correlation-Id' => 'req-' . bin2hex(random_bytes(4)),
            ],
            'json' => $payload,
        ]);

        self::assertSame(200, $createResponse->getStatusCode());
        /** @var array{status?: string, session_id?: string, webhook_id?: string} $createData */
        $createData = $this->decodeJson($createResponse);
        self::assertSame('captured', $createData['status'] ?? null);
        self::assertSame($sessionId, $createData['session_id'] ?? null);
        self::assertArrayHasKey('webhook_id', $createData);

        $listResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        self::assertSame(200, $listResponse->getStatusCode());
        /** @var list<array{
         *     method?: string,
         *     body?: mixed,
         *     headers?: array<string, string>,
         *     created_at?: string,
         *     id?: int
         * }> $webhooks */
        $webhooks = $this->decodeJson($listResponse);

        self::assertCount(1, $webhooks);
        $webhook = $webhooks[0];

        self::assertSame('POST', $webhook['method'] ?? null);
        self::assertSame($payload, $webhook['body'] ?? null);
        self::assertEquals(
            strtolower('application/json'),
            $webhook['headers']['content-type'] ?? null
        );
        self::assertEquals($signature, $webhook['headers']['x-webhook-signature'] ?? null);
        self::assertArrayHasKey('created_at', $webhook);
    }

    public function testSessionListingAndClearing(): void
    {
        $sessionA = $this->uniqueSession('e2e-session-a');
        $sessionB = $this->uniqueSession('e2e-session-b');

        foreach ([$sessionA, $sessionB] as $session) {
            $response = $this->request('POST', "/{$session}", [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['session' => $session],
            ]);
            self::assertSame(200, $response->getStatusCode());
        }

        $sessionsResponse = $this->request('GET', '/api/webhooks');
        self::assertSame(200, $sessionsResponse->getStatusCode());
        /** @var list<array{session_id?: string}> $sessions */
        $sessions = $this->decodeJson($sessionsResponse);

        $sessionIds = array_column($sessions, 'session_id');
        self::assertContains($sessionA, $sessionIds);
        self::assertContains($sessionB, $sessionIds);

        $deleteResponse = $this->request('DELETE', "/api/webhooks/{$sessionA}");
        self::assertSame(200, $deleteResponse->getStatusCode());
        /** @var array{status?: string, session_id?: string, deleted_count?: int} $deletePayload */
        $deletePayload = $this->decodeJson($deleteResponse);
        self::assertSame('cleared', $deletePayload['status'] ?? null);
        self::assertSame($sessionA, $deletePayload['session_id'] ?? null);
        self::assertSame(1, $deletePayload['deleted_count'] ?? null);

        $afterDeleteResponse = $this->request('GET', "/api/webhooks/{$sessionA}");
        self::assertSame(200, $afterDeleteResponse->getStatusCode());
        self::assertSame([], $this->decodeJson($afterDeleteResponse));
    }

    public function testMultipleWebhooksInSameSession(): void
    {
        $sessionId = $this->uniqueSession('e2e-multi');
        $payloads = [
            ['event' => 'order.created', 'id' => '1'],
            ['event' => 'order.updated', 'id' => '2'],
            ['event' => 'order.shipped', 'id' => '3'],
        ];

        foreach ($payloads as $payload) {
            $response = $this->request('POST', "/{$sessionId}", ['json' => $payload]);
            self::assertSame(200, $response->getStatusCode());
        }

        $listResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        /** @var list<array{
         *     method: string,
         *     body: mixed,
         *     id?: int,
         *     created_at?: string
         * }> $webhooks */
        $webhooks = $this->decodeJson($listResponse);

        self::assertCount(3, $webhooks);

        foreach ($webhooks as $index => $webhook) {
            self::assertSame('POST', $webhook['method']);
            self::assertSame($payloads[$index], $webhook['body']);
            self::assertArrayHasKey('id', $webhook);
            self::assertArrayHasKey('created_at', $webhook);
        }
    }

    public function testSessionIsolation(): void
    {
        $sessionA = $this->uniqueSession('isolation-a');
        $sessionB = $this->uniqueSession('isolation-b');

        $this->request('POST', "/{$sessionA}", ['json' => ['session' => 'A']]);
        $this->request('POST', "/{$sessionB}", ['json' => ['session' => 'B']]);

        /** @var list<array{body: mixed}> $webhooksA */
        $webhooksA = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionA}"));
        /** @var list<array{body: mixed}> $webhooksB */
        $webhooksB = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionB}"));

        self::assertCount(1, $webhooksA);
        self::assertCount(1, $webhooksB);
        self::assertSame(['session' => 'A'], $webhooksA[0]['body']);
        self::assertSame(['session' => 'B'], $webhooksB[0]['body']);
    }

    public function testEmptySessionReturnsEmptyArray(): void
    {
        $sessionId = $this->uniqueSession('empty');

        $response = $this->request('GET', "/api/webhooks/{$sessionId}");

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->decodeJson($response));
    }

    public function testDeleteNonExistentSessionReturnsSuccess(): void
    {
        $sessionId = $this->uniqueSession('non-existent');

        $response = $this->request('DELETE', "/api/webhooks/{$sessionId}");

        self::assertSame(200, $response->getStatusCode());
        /** @var array{status: string, session_id: string, deleted_count: int} $payload */
        $payload = $this->decodeJson($response);
        self::assertSame('cleared', $payload['status']);
        self::assertSame($sessionId, $payload['session_id']);
        self::assertSame(0, $payload['deleted_count']);
    }

    public function testNonJsonBodyIsStoredAsString(): void
    {
        $sessionId = $this->uniqueSession('plain-text');
        $bodyContent = 'This is plain text content, not JSON';

        $response = $this->request('POST', "/{$sessionId}", [
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => $bodyContent,
        ]);

        self::assertSame(200, $response->getStatusCode());

        /** @var list<array{body: mixed}> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));

        self::assertCount(1, $webhooks);
        self::assertSame($bodyContent, $webhooks[0]['body']);
    }

    public function testHeadersAreNormalizedToLowercase(): void
    {
        $sessionId = $this->uniqueSession('headers');

        $response = $this->request('POST', "/{$sessionId}", [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Custom-Header' => 'custom-value',
                'Authorization' => 'Bearer token123',
            ],
            'json' => ['test' => true],
        ]);

        self::assertSame(200, $response->getStatusCode());

        /** @var list<array{headers: array<string, string>}> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));
        $headers = $webhooks[0]['headers'];

        self::assertSame('application/json', $headers['content-type']);
        self::assertSame('custom-value', $headers['x-custom-header']);
        self::assertSame('Bearer token123', $headers['authorization']);
    }

    public function testWebhooksAreOrderedChronologically(): void
    {
        $sessionId = $this->uniqueSession('order');

        for ($i = 1; $i <= 5; $i++) {
            $this->request('POST', "/{$sessionId}", ['json' => ['order' => $i]]);
            usleep(10000); // Small delay to ensure different timestamps
        }

        /** @var list<array{body: array<string, int>}> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));

        self::assertCount(5, $webhooks);

        for ($i = 0; $i < 5; $i++) {
            self::assertSame($i + 1, $webhooks[$i]['body']['order']);
        }
    }
}
