<?php

declare(strict_types=1);

namespace App\Tests\E2E;

final class FailureSimulationTest extends BaseE2ETestCase
{
    public function testPermanentFailure500ReturnsExpectedStatusAndSkipsPersistence(): void
    {
        $sessionId = 'fail-500-' . $this->uniqueSession('permanent');
        $response = $this->request('POST', "/{$sessionId}", [
            'json' => ['attempt' => 1],
        ]);

        self::assertSame(500, $response->getStatusCode());
        /** @var array{error?: string} $payload */
        $payload = $this->decodeJson($response);
        self::assertSame('Internal Server Error (simulated)', $payload['error'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        self::assertSame([], $this->decodeJson($storedResponse));
    }

    public function testPermanentFailure503ReturnsExpectedStatus(): void
    {
        $sessionId = 'fail-503-' . $this->uniqueSession('unavailable');
        $response = $this->request('POST', "/{$sessionId}", ['json' => ['test' => true]]);

        self::assertSame(503, $response->getStatusCode());
        /** @var array{error?: string} $payload */
        $payload = $this->decodeJson($response);
        self::assertSame('Service Unavailable (simulated)', $payload['error'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        self::assertSame([], $this->decodeJson($storedResponse));
    }

    public function testPermanentFailure401ReturnsExpectedStatus(): void
    {
        $sessionId = 'fail-401-' . $this->uniqueSession('unauthorized');
        $response = $this->request('POST', "/{$sessionId}", ['json' => ['test' => true]]);

        self::assertSame(401, $response->getStatusCode());
        /** @var array{error?: string} $payload */
        $payload = $this->decodeJson($response);
        self::assertSame('Unauthorized (simulated)', $payload['error'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        self::assertSame([], $this->decodeJson($storedResponse));
    }

    public function testPermanentFailure403ReturnsExpectedStatus(): void
    {
        $sessionId = 'fail-403-' . $this->uniqueSession('forbidden');
        $response = $this->request('POST', "/{$sessionId}", ['json' => ['test' => true]]);

        self::assertSame(403, $response->getStatusCode());
        /** @var array{error?: string} $payload */
        $payload = $this->decodeJson($response);
        self::assertSame('Forbidden (simulated)', $payload['error'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        self::assertSame([], $this->decodeJson($storedResponse));
    }

    public function testStatefulFailure2xThenOkEventuallySucceeds(): void
    {
        $sessionId = 'fail-2x-then-ok-' . $this->uniqueSession('stateful');
        $responses = [];

        foreach ([1, 2, 3] as $attempt) {
            $response = $this->request('POST', "/{$sessionId}", [
                'json' => ['attempt' => $attempt],
            ]);
            $response->getStatusCode(); // Force request to complete before next iteration
            $responses[$attempt] = $response;
        }

        self::assertSame(500, $responses[1]->getStatusCode());
        self::assertSame(500, $responses[2]->getStatusCode());
        self::assertSame(200, $responses[3]->getStatusCode());

        /** @var array{error?: string, attempt?: int} $firstPayload */
        $firstPayload = $this->decodeJson($responses[1]);
        /** @var array{attempt?: int} $secondPayload */
        $secondPayload = $this->decodeJson($responses[2]);
        self::assertSame('Simulated failure', $firstPayload['error'] ?? null);
        self::assertSame(1, $firstPayload['attempt'] ?? null);
        self::assertSame(2, $secondPayload['attempt'] ?? null);

        /** @var array{status?: string} $successPayload */
        $successPayload = $this->decodeJson($responses[3]);
        self::assertSame('captured', $successPayload['status'] ?? null);

        $storedResponse = $this->request('GET', "/api/webhooks/{$sessionId}");
        /** @var list<array{method?: string, body?: mixed}> $storedWebhooks */
        $storedWebhooks = $this->decodeJson($storedResponse);
        self::assertCount(3, $storedWebhooks);

        foreach ($storedWebhooks as $index => $webhook) {
            $attemptIdx = $index + 1;
            self::assertSame('POST', $webhook['method'] ?? null);
            self::assertSame(['attempt' => $attemptIdx], $webhook['body'] ?? null);
        }
    }

    public function testStatefulFailure1xThenOkSucceedsOnSecondAttempt(): void
    {
        $sessionId = 'fail-1x-then-ok-' . $this->uniqueSession('stateful');

        $firstResponse = $this->request('POST', "/{$sessionId}", ['json' => ['attempt' => 1]]);
        self::assertSame(500, $firstResponse->getStatusCode());

        $secondResponse = $this->request('POST', "/{$sessionId}", ['json' => ['attempt' => 2]]);
        self::assertSame(200, $secondResponse->getStatusCode());

        /** @var list<array{body: mixed}> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));
        self::assertCount(2, $webhooks);
    }

    public function testStatefulFailure5xThenOkSucceedsOnSixthAttempt(): void
    {
        $sessionId = 'fail-5x-then-ok-' . $this->uniqueSession('stateful');

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->request('POST', "/{$sessionId}", ['json' => ['attempt' => $i]]);
            self::assertSame(500, $response->getStatusCode());
        }

        $successResponse = $this->request('POST', "/{$sessionId}", ['json' => ['attempt' => 6]]);
        self::assertSame(200, $successResponse->getStatusCode());

        /** @var list<array{body: mixed}> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));
        self::assertCount(6, $webhooks);
    }

    public function testDelaySimulation3Seconds(): void
    {
        $sessionId = 'delay-3s-' . $this->uniqueSession('delay');

        $startTime = microtime(true);
        $response = $this->request('POST', "/{$sessionId}", ['json' => ['delayed' => true]]);
        self::assertSame(200, $response->getStatusCode());
        $duration = microtime(true) - $startTime;

        self::assertGreaterThanOrEqual(2.9, $duration, 'Should delay at least 3 seconds');
        self::assertLessThanOrEqual(3.5, $duration, 'Should not delay more than 3.5 seconds');

        /** @var list<array{body: mixed}> $webhooks */
        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));
        self::assertCount(1, $webhooks);
        self::assertSame(['delayed' => true], $webhooks[0]['body']);
    }

    public function testDelaySimulation5Seconds(): void
    {
        $sessionId = 'delay-5s-' . $this->uniqueSession('delay');

        $startTime = microtime(true);
        $response = $this->request('POST', "/{$sessionId}", ['json' => ['delayed' => true]]);
        self::assertSame(200, $response->getStatusCode());
        $duration = microtime(true) - $startTime;

        self::assertGreaterThanOrEqual(4.9, $duration, 'Should delay at least 5 seconds');
        self::assertLessThanOrEqual(5.5, $duration, 'Should not delay more than 5.5 seconds');

        $webhooks = $this->decodeJson($this->request('GET', "/api/webhooks/{$sessionId}"));
        self::assertCount(1, $webhooks);
    }

    public function testStatefulRetrySessionsAreIndependent(): void
    {
        $session1 = 'fail-2x-then-ok-' . $this->uniqueSession('independent-1');
        $session2 = 'fail-2x-then-ok-' . $this->uniqueSession('independent-2');

        $r1 = $this->request('POST', "/{$session1}", ['json' => ['session' => 1, 'attempt' => 1]]);
        $r1->getStatusCode(); // Force completion

        $r2 = $this->request('POST', "/{$session2}", ['json' => ['session' => 2, 'attempt' => 1]]);
        $r2->getStatusCode(); // Force completion

        $r3 = $this->request('POST', "/{$session1}", ['json' => ['session' => 1, 'attempt' => 2]]);
        $r3->getStatusCode(); // Force completion

        $response1 = $this->request('POST', "/{$session1}", ['json' => ['session' => 1, 'attempt' => 3]]);
        $response2 = $this->request('POST', "/{$session2}", ['json' => ['session' => 2, 'attempt' => 2]]);

        self::assertSame(200, $response1->getStatusCode(), 'Session 1 should succeed on 3rd attempt');
        self::assertSame(500, $response2->getStatusCode(), 'Session 2 should still fail on 2nd attempt');
    }
}
