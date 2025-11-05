<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class BaseE2ETestCase extends TestCase
{
    private static ?HttpClientInterface $client = null;
    private static ?string $baseUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$client === null) {
            self::$client = HttpClient::create(['timeout' => 10]);
        }

        if (self::$baseUrl === null) {
            $configuredBaseUrl = getenv('E2E_BASE_URL');
            if ($configuredBaseUrl === false || $configuredBaseUrl === '') {
                $configuredBaseUrl = 'http://127.0.0.1:8080';
            }

            self::$baseUrl = rtrim($configuredBaseUrl, '/');
            $this->waitForServer();
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        if (self::$client === null || self::$baseUrl === null) {
            throw new RuntimeException('E2E HTTP client is not initialised.');
        }

        $headers = $options['headers'] ?? [];
        if (! is_array($headers)) {
            throw new RuntimeException('Expected headers option to be an array.');
        }

        $options['headers'] = array_merge(
            [
                'Accept' => 'application/json',
            ],
            $headers
        );

        return self::$client->request($method, self::$baseUrl . $uri, $options);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function decodeJson(ResponseInterface $response): array
    {
        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($response->getContent(false), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    protected function uniqueSession(string $prefix): string
    {
        return sprintf('%s-%s', $prefix, bin2hex(random_bytes(5)));
    }

    private function waitForServer(): void
    {
        if (self::$client === null || self::$baseUrl === null) {
            return;
        }

        $deadline = time() + 60;
        do {
            try {
                $response = self::$client->request('GET', self::$baseUrl . '/');
                $status = $response->getStatusCode();
                if ($status >= 200 && $status < 500) {
                    return;
                }
            } catch (\Throwable) {
                // Ignore and retry
            }

            usleep(250_000);
        } while (time() < $deadline);

        throw new RuntimeException(sprintf('Unable to reach E2E server at "%s".', self::$baseUrl));
    }
}
