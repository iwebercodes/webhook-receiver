<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\WebhookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly WebhookRepository $webhookRepository
    ) {
    }

    #[Route('/', name: 'app_home', priority: 10)]
    public function index(): JsonResponse
    {
        $sessions = $this->webhookRepository->getSessionsWithDetails();

        return new JsonResponse([
            'service' => 'Webhook Receiver',
            'version' => '1.0.0',
            'endpoints' => [
                'POST /{session_id}' => 'Capture webhook',
                'GET /api/webhooks/{session_id}' => 'Retrieve webhooks',
                'DELETE /api/webhooks/{session_id}' => 'Clear webhooks',
                'GET /api/webhooks' => 'List sessions',
            ],
            'sessions' => $sessions,
        ]);
    }
}
