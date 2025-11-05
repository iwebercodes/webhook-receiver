# Webhook Receiver for Testing

A self-hosted webhook receiver service built with Symfony and SQLite for testing webhook integrations.

## Features

- **Capture webhooks** - Receives POST requests and stores them with full details
- **HTTP headers** - Captures and returns all HTTP headers (including custom headers like `X-Webhook-Signature`)
- **Request body** - Stores raw body and automatically parses JSON
- **REST API** - Retrieve, list, and clear captured webhooks programmatically
- **Session-based** - Organize webhooks by session ID for parallel test isolation
- **Failure simulation** - Test retry logic with configurable errors, timeouts, and delays
- **SQLite storage** - Lightweight database with persistent storage via Docker volume
- **Docker ready** - Pre-built image available, runs anywhere

## API Endpoints

### Capture Webhook
```http
POST /{session_id}
```

Captures a webhook and stores it with all headers and body.

**Example:**
```bash
curl -X POST http://localhost:8080/my-test-session \
  -H 'Content-Type: application/json' \
  -H 'X-Webhook-Signature: sha256=abc123' \
  -d '{"event":"order.created","data":{"id":"12345"}}'
```

**Response:**
```json
{
  "status": "captured",
  "session_id": "my-test-session",
  "webhook_id": "1"
}
```

### Retrieve Webhooks
```http
GET /api/webhooks/{session_id}
```

Returns all webhooks captured for a specific session.

**Example:**
```bash
curl http://localhost:8080/api/webhooks/my-test-session
```

**Response:**
```json
[
  {
    "id": 1,
    "method": "POST",
    "headers": {
      "host": "localhost:8080",
      "content-type": "application/json",
      "x-webhook-signature": "sha256=abc123"
    },
    "body": {
      "event": "order.created",
      "data": {
        "id": "12345"
      }
    },
    "created_at": "2025-10-23 15:30:45"
  }
]
```

### List All Sessions
```http
GET /api/webhooks
```

Returns all active sessions with webhook counts.

**Example:**
```bash
curl http://localhost:8080/api/webhooks
```

**Response:**
```json
[
  {
    "session_id": "my-test-session",
    "count": 3
  },
  {
    "session_id": "another-session",
    "count": 1
  }
]
```

### Clear Webhooks
```http
DELETE /api/webhooks/{session_id}
```

Deletes all webhooks for a specific session.

**Example:**
```bash
curl -X DELETE http://localhost:8080/api/webhooks/my-test-session
```

**Response:**
```json
{
  "status": "cleared",
  "session_id": "my-test-session",
  "deleted_count": 3
}
```

### Service Info
```http
GET /
```

Returns service information and all sessions.

## Failure Simulation for Testing Retry Logic

Use special session ID patterns to simulate various failure scenarios:

### Always Fail with HTTP Error
```bash
# Always returns 500 Internal Server Error
curl -X POST http://localhost:8080/fail-500-test-session

# Always returns 503 Service Unavailable
curl -X POST http://localhost:8080/fail-503-test-session

# Always returns 401 Unauthorized
curl -X POST http://localhost:8080/fail-401-test-session

# Always returns 403 Forbidden
curl -X POST http://localhost:8080/fail-403-test-session
```

### Timeout Simulation
```bash
# Sleeps for 15 seconds (exceeds typical 10s timeout)
curl -X POST http://localhost:8080/fail-timeout-test-session
```

### Stateful Retry Testing
```bash
# Fails first 2 attempts with 500, then succeeds on 3rd attempt
curl -X POST http://localhost:8080/fail-2x-then-ok-test-session

# Fails first attempt with 500, then succeeds on 2nd attempt
curl -X POST http://localhost:8080/fail-1x-then-ok-test-session
```

These patterns are perfect for testing webhook retry logic with exponential backoff.

### Delay Simulation
```bash
# Delays 3 seconds then returns 200 OK
curl -X POST http://localhost:8080/delay-3s-test-session

# Delays 5 seconds then returns 200 OK
curl -X POST http://localhost:8080/delay-5s-test-session
```

### Authentication Testing
```bash
# Requires X-Webhook-Signature header, returns 401 if missing
curl -X POST http://localhost:8080/require-auth-test-session \
  -H 'X-Webhook-Signature: sha256=abc123'
```

### Normal Success
```bash
# Any other session ID returns 200 OK immediately
curl -X POST http://localhost:8080/normal-test-session
```

## Usage in Tests

### PHP Integration Test Example

```php
class WebhookIntegrationTest extends TestCase
{
    private string $sessionId;

    protected function setUp(): void
    {
        // Use unique session ID per test for isolation
        $this->sessionId = Uuid::randomHex();

        // Configure webhook to point to this session
        $this->configureWebhook(
            "http://webhook-receiver:80/{$this->sessionId}"
        );
    }

    public function testWebhookDelivery(): void
    {
        // Trigger action that sends webhook
        $this->createOrder();

        // Wait for webhook delivery
        sleep(2);

        // Retrieve captured webhooks
        $response = $this->httpClient->request(
            'GET',
            "http://webhook-receiver:80/api/webhooks/{$this->sessionId}"
        );

        $webhooks = json_decode($response->getContent(), true);

        // Assert webhook was received
        $this->assertCount(1, $webhooks);

        // Verify headers
        $this->assertArrayHasKey('x-webhook-signature', $webhooks[0]['headers']);

        // Verify body
        $this->assertEquals('order.created', $webhooks[0]['body']['event']);
    }

    protected function tearDown(): void
    {
        // Clean up after test
        $this->httpClient->request(
            'DELETE',
            "http://webhook-receiver:80/api/webhooks/{$this->sessionId}"
        );
    }
}
```

## Quick Start

### Using Pre-built Image

```bash
docker run -d -p 8080:80 \
  -v webhook-data:/var/www/html/var/data \
  iwebercodes/webhook-receiver:latest
```

### Using Docker Compose

Create `compose.yml`:
```yaml
services:
  webhook-receiver:
    image: iwebercodes/webhook-receiver:latest
    ports:
      - "8080:80"
    volumes:
      - webhook-data:/var/www/html/var/data

volumes:
  webhook-data:
```

Then run:
```bash
docker compose up -d
```

### Building from Source

```bash
# Clone the repository
git clone https://github.com/iwebercodes/webhook-receiver.git
cd webhook-receiver

# Build the image
./build.sh

# Start the service
docker compose up -d
```

### Accessing the Service

From within Docker network:
```
http://webhook-receiver/{session_id}
```

From host machine:
```
http://localhost:8080/{session_id}
```

## Testing Headers

The receiver captures **all HTTP headers**, including:
- Standard headers: `Content-Type`, `User-Agent`, `Host`
- Custom headers: `X-Webhook-Signature`, `X-Request-ID`, etc.
- Authentication headers: `Authorization`

Headers are normalized to lowercase in the response for consistency.

**Example:**
```bash
curl -X POST http://localhost:8080/test \
  -H 'X-Webhook-Signature: sha256=abc' \
  -H 'X-Custom-Header: value' \
  -d '{"test":true}'

curl http://localhost:8080/api/webhooks/test | jq '.[0].headers'
```

**Output:**
```json
{
  "x-webhook-signature": "sha256=abc",
  "x-custom-header": "value",
  "content-type": "application/x-www-form-urlencoded",
  "host": "localhost:8080"
}
```

## Data Persistence

Webhooks are stored in SQLite database at `/var/www/html/var/data/webhooks.db` inside the container.

The database is persisted via Docker volume `webhook-data`, so data survives container restarts.

To clear all data:
```bash
docker compose down -v  # Removes volumes
docker compose up -d
```


## Why We Built This

We needed a webhook receiver for integration tests that:
1. Provides a **REST API** to retrieve webhooks programmatically
2. Supports **session-based isolation** for parallel tests
3. Captures **full HTTP headers** for signature verification
4. Is **self-hosted** and runs in Docker
5. Has **no external dependencies** or accounts required

Existing solutions like `webhook.site` and `webhook-tester` didn't meet all requirements.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) file for details.

Copyright (c) 2025 Ilja Weber
