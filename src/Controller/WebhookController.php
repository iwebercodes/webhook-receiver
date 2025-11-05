<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    private const SIMULATED_ERRORS = [
        'fail-500-' => [
            'message' => 'Internal Server Error (simulated)',
            'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
        ],
        'fail-503-' => [
            'message' => 'Service Unavailable (simulated)',
            'status' => Response::HTTP_SERVICE_UNAVAILABLE,
        ],
        'fail-401-' => [
            'message' => 'Unauthorized (simulated)',
            'status' => Response::HTTP_UNAUTHORIZED,
        ],
        'fail-403-' => [
            'message' => 'Forbidden (simulated)',
            'status' => Response::HTTP_FORBIDDEN,
        ],
    ];
    private const FAIL_THEN_OK_PATTERN = '/^fail-(\d+)x-then-ok-/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebhookRepository $webhookRepository
    ) {
    }

    #[Route(
        '/{sessionId}',
        name: 'webhook_capture',
        methods: ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'],
        requirements: ['sessionId' => '[^/]+']
    )]
    public function capture(string $sessionId, Request $request): JsonResponse
    {
        $headers = $this->normalizeHeaders($request->headers->all());
        $body = $request->getContent();

        $simulationResponse = $this->handleFailureSimulation(
            $sessionId,
            $headers,
            $body
        );
        if ($simulationResponse !== null) {
            return $simulationResponse;
        }

        $normalizedBody = $body === '' ? null : $body;
        $method = $request->getMethod();
        $webhook = $this->recordWebhook(
            $sessionId,
            $method,
            $headers,
            $normalizedBody
        );

        return $this->buildCaptureResponse($sessionId, $webhook);
    }

    #[Route(
        '/api/webhooks/{sessionId}',
        name: 'webhook_list',
        methods: ['GET']
    )]
    public function list(string $sessionId): JsonResponse
    {
        $webhooks = $this->webhookRepository->findBySessionId($sessionId);

        $data = array_map(function (Webhook $webhook) {
            return [
                'id' => $webhook->getId(),
                'method' => $webhook->getMethod(),
                'headers' => $webhook->getHeaders(),
                'body' => $this->parseBody($webhook->getBody()),
                'created_at' => $webhook->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $webhooks);

        return new JsonResponse($data);
    }

    #[Route('/api/webhooks', name: 'webhook_list_sessions', methods: ['GET'])]
    public function listSessions(): JsonResponse
    {
        $sessions = $this->webhookRepository->getAllSessions();

        return new JsonResponse($sessions);
    }

    #[Route(
        '/api/webhooks/{sessionId}',
        name: 'webhook_clear',
        methods: ['DELETE']
    )]
    public function clear(string $sessionId): JsonResponse
    {
        $deletedCount = $this->webhookRepository->deleteBySessionId($sessionId);

        return new JsonResponse([
            'status' => 'cleared',
            'session_id' => $sessionId,
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * @param array<string, list<string|null>> $headers
     *
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $values) {
            $normalized[strtolower($key)] = $values[0] ?? '';
        }

        return $normalized;
    }

    private function parseBody(?string $body): mixed
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $body;
    }

    /**
     * @param array<string, string> $headers
     */
    private function handleFailureSimulation(
        string $sessionId,
        array $headers,
        ?string $body
    ): ?JsonResponse {
        $response = $this->simulateHttpError($sessionId);
        if ($response !== null) {
            return $response;
        }

        $response = $this->simulateTimeout($sessionId);
        if ($response !== null) {
            return $response;
        }

        $response = $this->simulateRetryThenSucceed(
            $sessionId,
            $headers,
            $body ?? ''
        );
        if ($response !== null) {
            return $response;
        }

        $this->simulateDelay($sessionId);

        return $this->requireAuthIfNeeded($sessionId, $headers);
    }

    private function simulateHttpError(string $sessionId): ?JsonResponse
    {
        foreach (self::SIMULATED_ERRORS as $prefix => $config) {
            if (! str_starts_with($sessionId, $prefix)) {
                continue;
            }

            return new JsonResponse(
                ['error' => $config['message']],
                $config['status']
            );
        }

        return null;
    }

    private function simulateTimeout(string $sessionId): ?JsonResponse
    {
        if (preg_match('/^fail-timeout-/', $sessionId) !== 1) {
            return null;
        }

        sleep(15);

        return new JsonResponse(['status' => 'captured_after_timeout']);
    }

    /**
     * @param array<string, string> $headers
     */
    private function simulateRetryThenSucceed(
        string $sessionId,
        array $headers,
        string $body
    ): ?JsonResponse {
        if (
            preg_match(self::FAIL_THEN_OK_PATTERN, $sessionId, $matches) !== 1
        ) {
            return null;
        }

        $failCount = (int) $matches[1];
        $attemptCount = $this->webhookRepository->countBySessionId($sessionId);

        if ($attemptCount >= $failCount) {
            return null;
        }

        $recordedBody = $body === '' ? null : $body;
        $this->recordWebhook($sessionId, 'POST', $headers, $recordedBody);

        return $this->createSimulatedFailureResponse($attemptCount, $failCount);
    }

    private function simulateDelay(string $sessionId): void
    {
        if (preg_match('/^delay-(\d+)s-/', $sessionId, $matches) === 1) {
            sleep((int) $matches[1]);
        }
    }

    private function createSimulatedFailureResponse(
        int $attemptCount,
        int $failCount
    ): JsonResponse {
        return new JsonResponse(
            [
                'error' => 'Simulated failure',
                'attempt' => $attemptCount + 1,
                'will_succeed_after' => $failCount,
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function requireAuthIfNeeded(
        string $sessionId,
        array $headers
    ): ?JsonResponse {
        if (preg_match('/^require-auth-/', $sessionId) !== 1) {
            return null;
        }

        $signature = $headers['x-webhook-signature'] ?? null;
        if (! is_string($signature) || $signature === '') {
            return new JsonResponse(
                ['error' => 'Missing X-Webhook-Signature header'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return null;
    }

    private function buildCaptureResponse(
        string $sessionId,
        Webhook $webhook
    ): JsonResponse {
        return new JsonResponse([
            'status' => 'captured',
            'session_id' => $sessionId,
            'webhook_id' => (string) $webhook->getId(),
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function recordWebhook(
        string $sessionId,
        string $method,
        array $headers,
        ?string $body
    ): Webhook {
        $webhook = new Webhook($sessionId, $method, $headers, $body);
        $this->entityManager->persist($webhook);
        $this->entityManager->flush();

        return $webhook;
    }
}
