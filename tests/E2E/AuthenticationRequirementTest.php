<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class AuthenticationRequirementTest extends BaseE2ETestCase
{
    public function testSessionRequiringSignatureRejectsMissingHeader(): void
    {
        $sessionId = 'require-auth-' . $this->uniqueSession('auth');

        $unauthenticated = $this->request('POST', "/{$sessionId}", [
            'json' => ['event' => 'payment.received'],
        ]);

        self::assertSame(401, $unauthenticated->getStatusCode());
        /** @var array{error?: string} $payload */
        $payload = $this->decodeJson($unauthenticated);
        self::assertSame('Missing X-Webhook-Signature header', $payload['error'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        self::assertSame([], $this->decodeJson($storedResponse));
    }

    public function testSessionRequiringSignatureAcceptsValidHeader(): void
    {
        $sessionId = 'require-auth-' . $this->uniqueSession('auth-ok');
        $signature = 'sha256=' . bin2hex(random_bytes(6));

        $response = $this->request('POST', "/{$sessionId}", [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
            ],
            'json' => ['event' => 'payment.received'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        /** @var array{status?: string} $payload */
        $payload = $this->decodeJson($response);
        self::assertSame('captured', $payload['status'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        /** @var list<array{headers: array<string, string>}> $webhooks */
        $webhooks = $this->decodeJson($storedResponse);
        self::assertCount(1, $webhooks);
        self::assertSame($signature, $webhooks[0]['headers']['x-webhook-signature'] ?? null);
    }

    public function testSessionRequiringSignatureAcceptsAnyNonEmptySignature(): void
    {
        $sessionId = 'require-auth-' . $this->uniqueSession('any-sig');

        $testSignatures = [
            'simple-value',
            'sha256=abcdef123456',
            'hmac-sha512=xyz',
            'Bearer token123',
        ];

        foreach ($testSignatures as $signature) {
            $response = $this->request('POST', "/{$sessionId}", [
                'headers' => ['X-Webhook-Signature' => $signature],
                'json' => ['signature' => $signature],
            ]);

            self::assertSame(200, $response->getStatusCode(), "Signature '{$signature}' should be accepted");
        }

        /** @var list<array<string, mixed>> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));
        self::assertCount(count($testSignatures), $webhooks);
    }
}
