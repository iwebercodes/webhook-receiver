<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WebhookRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookRepository::class)]
#[ORM\Table(name: 'webhooks')]
#[ORM\Index(columns: ['session_id'], name: 'idx_session_id')]
class Webhook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $sessionId;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $method;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $headers;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        string $sessionId,
        string $method,
        array $headers,
        ?string $body = null,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->sessionId = $sessionId;
        $this->method = $method;
        $this->headers = $headers;
        $this->body = $body;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
