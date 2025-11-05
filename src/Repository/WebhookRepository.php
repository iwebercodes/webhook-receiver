<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Webhook;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Webhook>
 */
final class WebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Webhook::class);
    }

    /**
     * @return list<Webhook>
     */
    public function findBySessionId(string $sessionId): array
    {
        $query = $this->createQueryBuilder('w')
            ->where('w.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery();

        /** @var list<Webhook> $webhooks */
        $webhooks = $query->getResult();

        if ($webhooks === []) {
            return [];
        }

        return $webhooks;
    }

    public function countBySessionId(string $sessionId): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteBySessionId(string $sessionId): int
    {
        $result = $this->createQueryBuilder('w')
            ->delete()
            ->where('w.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->execute();

        if (! is_int($result)) {
            throw new \RuntimeException('Unexpected delete result type.');
        }

        return $result;
    }

    /**
     * @return list<array{session_id: string, count: int}>
     */
    public function getAllSessions(): array
    {
        $query = $this->createQueryBuilder('w')
            ->select('w.sessionId AS session_id')
            ->addSelect('COUNT(w.id) AS count')
            ->groupBy('w.sessionId')
            ->orderBy('MAX(w.createdAt)', 'DESC')
            ->getQuery();

        /** @var list<array{session_id: string, count: string|int}> $result */
        $result = $query->getResult();

        return array_map(
            function (array $row): array {
                return $this->formatSessionSummaryRow($row);
            },
            $result
        );
    }

    /**
     * @return list<array{
     *     session_id: string,
     *     webhook_count: int,
     *     last_webhook: string
     * }>
     */
    public function getSessionsWithDetails(): array
    {
        $query = $this->createQueryBuilder('w')
            ->select('w.sessionId AS session_id')
            ->addSelect('COUNT(w.id) AS webhook_count')
            ->addSelect('MAX(w.createdAt) AS last_webhook')
            ->groupBy('w.sessionId')
            ->orderBy('MAX(w.createdAt)', 'DESC')
            ->getQuery();

        /**
         * @var list<array{
         *     session_id: string,
         *     webhook_count: string|int,
         *     last_webhook: \DateTimeInterface|string
         * }> $result
         */
        $result = $query->getResult();

        return array_map(
            function (array $row): array {
                return $this->formatSessionDetailsRow($row);
            },
            $result
        );
    }

    /**
     * @param array{session_id: string, count: string|int} $row
     *
     * @return array{session_id: string, count: int}
     */
    private function formatSessionSummaryRow(array $row): array
    {
        return [
            'session_id' => $row['session_id'],
            'count' => (int) $row['count'],
        ];
    }

    /**
     * @param array{
     *     session_id: string,
     *     webhook_count: string|int,
     *     last_webhook: \DateTimeInterface|string
     * } $row
     *
     * @return array{
     *     session_id: string,
     *     webhook_count: int,
     *     last_webhook: string
     * }
     */
    private function formatSessionDetailsRow(array $row): array
    {
        $lastWebhook = $row['last_webhook'];
        if ($lastWebhook instanceof \DateTimeInterface) {
            $formatted = $lastWebhook->format('Y-m-d H:i:s');
        } elseif (is_string($lastWebhook)) {
            $formatted = $lastWebhook;
        } else {
            throw new \RuntimeException('Unexpected last_webhook type.');
        }

        return [
            'session_id' => $row['session_id'],
            'webhook_count' => (int) $row['webhook_count'],
            'last_webhook' => $formatted,
        ];
    }
}
