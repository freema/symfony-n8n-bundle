# Symfony N8n Bundle

Elegant integration between Symfony applications and n8n workflow automation platform.

## Features

- **Type-safe communication** using PHP interfaces
- **UUID tracking system** for request/response pairing
- **Flexible communication modes**: Fire & Forget, Async with callback, Sync
- **Robust error handling** with retry and circuit breaker
- **Event-driven architecture** for monitoring and logging
- **Multi-instance support** for different environments
- **Dry run mode** for testing without actual sending

## Requirements

| Version  | PHP  | Symfony  |
|----------|------|----------|
| 2.x      | ^8.2 | 6.4, 7.x |
| 2.x      | ^8.4 | 8.0      |
| 1.3      | ^8.2 | 6.4, 7.x |
| 1.0-1.2  | ^8.1 | 6.4, 7.0 |

> **Note:** Symfony 8.0 requires PHP 8.4+

## Quick Start

### 1. Installation

```bash
composer require freema/n8n-bundle
```

### 2. Development with Docker and Taskfile

```bash
# Install Task (https://taskfile.dev)
brew install go-task/tap/go-task

# Initialize development environment
task init

# Start dev server
task serve

# Show available commands
task --list
```

### 3. Configuration

```yaml
# config/packages/n8n.yaml
n8n:
  clients:
    default:
      base_url: 'https://your-n8n-instance.com'
      client_id: 'my-symfony-app'
      auth_token: '%env(N8N_AUTH_TOKEN)%'
      timeout_seconds: 30
      retry_attempts: 3
      enable_circuit_breaker: true
      proxy: '%env(HTTP_PROXY)%'  # Optional: HTTP proxy URL
      use_test_webhook: false  # Optional: use /webhook-test/ for unpublished workflows (default: false)
```

### 4. Entity Implementation

```php
<?php

use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Enum\RequestMethod;

class ForumPost implements N8nPayloadInterface
{
    public function toN8nPayload(): array
    {
        return [
            'text' => $this->content,
            'author_id' => $this->authorId,
            'created_at' => $this->createdAt->format(DATE_ATOM)
        ];
    }
    
    public function getN8nContext(): array
    {
        return [
            'entity_type' => 'forum_post',
            'entity_id' => $this->id,
            'action' => 'moderate'
        ];
    }

    // Optional: define HTTP method and content type
    public function getN8nRequestMethod(): RequestMethod
    {
        return RequestMethod::POST_FORM; // or POST_JSON, GET, etc.
    }

    // Optional: custom response handler
    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface
    {
        return new ModerationResponseHandler();
    }

    // Optional: response entity mapping
    public function getN8nResponseClass(): ?string
    {
        return ModerationResponse::class;
    }
}
```

### 5. Usage

```php
<?php

// Fire & Forget - returns an N8nResponse object immediately
$response = $n8nClient->send($post, 'workflow-id');
$response->getUuid();           // request UUID
$response->getResponse();       // raw response data (array)
$response->getMappedResponse(); // mapped entity (object) or null
$response->getStatusCode();     // HTTP status code
$response->isSuccess();         // true for 2xx
$response->toArray();           // ['uuid' => ..., 'response' => ..., 'mapped_response' => ..., 'status_code' => ...]

// Async with callback
$uuid = $n8nClient->sendWithCallback($post, 'workflow-id', $responseHandler);

// Sync
$response = $n8nClient->sendSync($post, 'workflow-id');
```

## Communication Modes

### Fire & Forget
Sends data to n8n and returns immediate response from webhook.

```php
$response = $n8nClient->send($payload, 'workflow-id');
// Returns an N8nResponse object: getUuid(), getResponse(), getMappedResponse(), getStatusCode(), isSuccess(), toArray()
```

### Async with Callback
Sends data + callback URL, n8n processes and returns result.

```php
class MyResponseHandler implements N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        // Process response from n8n
    }
    
    public function getHandlerId(): string
    {
        return 'my_handler';
    }
}

$uuid = $n8nClient->sendWithCallback($payload, 'workflow-id', new MyResponseHandler());
```

### Sync
Waits for immediate response (if n8n webhook supports it).

```php
$response = $n8nClient->sendSync($payload, 'workflow-id', 30); // 30s timeout
```

## Error Handling

All errors thrown by the client extend `Freema\N8nBundle\Exception\N8nException`:

| Exception | Thrown when |
|-----------|-------------|
| `N8nException` | Base class for every bundle exception |
| `N8nCommunicationException` | HTTP error status (`>= 400`, code = status), a transport failure, or an open circuit breaker |
| `N8nTimeoutException` | The request timed out (extends `N8nCommunicationException`) |

```php
use Freema\N8nBundle\Exception\N8nException;
use Freema\N8nBundle\Exception\N8nTimeoutException;

try {
    $response = $n8nClient->send($payload, 'workflow-id');
} catch (N8nTimeoutException $e) {
    // request timed out (after retries)
} catch (N8nException $e) {
    // any other communication error; $e->getCode() holds the HTTP status for error responses
}
```

### Retries

When `retry_attempts > 0`, failed requests are retried with exponential backoff
(`retry_delay_ms`, doubled each attempt). Retries apply to **transport errors,
timeouts and HTTP 5xx** responses. Client errors (4xx) are **not** retried.

### Circuit Breaker

With `enable_circuit_breaker: true`, the client opens the circuit after
`circuit_breaker_threshold` consecutive failures and rejects further calls
(throwing `N8nCommunicationException`) for `circuit_breaker_timeout_seconds`
before trying again. A successful call resets the breaker.

> The circuit breaker is in-memory, so its state lives for the duration of a single
> PHP process — it is most useful in long-running workers (e.g. Symfony Messenger).

### Test Webhooks

During development you can target n8n's *test* webhook URL (`/webhook-test/`) instead
of the production one (`/webhook/`) to use unpublished workflows:

```yaml
n8n:
  clients:
    default:
      use_test_webhook: true  # default: false
```

## HTTP Methods and Content Types

Bundle supports various HTTP methods and content types:

```php
use Freema\N8nBundle\Enum\RequestMethod;

class MyPayload implements N8nPayloadInterface
{
    public function getN8nRequestMethod(): RequestMethod
    {
        return RequestMethod::POST_FORM;  // Form data (application/x-www-form-urlencoded)
        // return RequestMethod::POST_JSON;  // JSON body (application/json)
        // return RequestMethod::GET;        // GET parameters
        // return RequestMethod::PUT_JSON;   // PUT with JSON
        // return RequestMethod::PATCH_FORM; // PATCH with form data
    }
}
```

## Response Entity Mapping

You can automatically map n8n responses to PHP objects:

```php
// 1. Create response entity
class ModerationResponse
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $confidence = null
    ) {}
}

// 2. Specify class in payload
class ForumPost implements N8nPayloadInterface
{
    public function getN8nResponseClass(): ?string
    {
        return ModerationResponse::class;
    }
}

// 3. Use mapped object
$response = $n8nClient->send($post, 'workflow-id');
$mappedResponse = $response->getMappedResponse(); // Instance of ModerationResponse
$isAllowed = $mappedResponse->allowed; // Type-safe access
```

## Debug Panel

Bundle includes debug panel for Symfony Web Profiler:

```yaml
# config/packages/n8n.yaml
n8n:
  debug:
    enabled: true  # or null for auto-detection based on kernel.debug
    log_requests: true
```

Panel shows:
- All N8n requests with UUID, duration, status
- Payload data and response data  
- Errors and their details
- Total request count and time

## Monitoring and Events

Bundle emits events for each phase of communication:

```php
// Event listener
class N8nMonitoringListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            N8nRequestSentEvent::NAME => 'onRequestSent',
            N8nResponseReceivedEvent::NAME => 'onResponseReceived',
            N8nRequestFailedEvent::NAME => 'onRequestFailed',
            N8nRetryEvent::NAME => 'onRetry',
        ];
    }
    
    public function onRequestSent(N8nRequestSentEvent $event): void
    {
        // Log, metrics, monitoring...
    }
}
```

## Test Application

The `dev/` directory contains a test application with Docker support (PHP 8.2 + PHP 8.4).

### Setup

1. Start Docker containers:
```bash
task up
```

2. Install dependencies:
```bash
task init
```

3. Environment configuration:
```bash
# Copy example file
cp dev/.env.example dev/.env.local

# Edit dev/.env.local and fill in:
# - N8N_WEBHOOK_FIRE_AND_FORGET - your webhook ID
# - N8N_WEBHOOK_WITH_CALLBACK - your webhook ID for callback
# - N8N_CALLBACK_BASE_URL - URL where your application runs (for callback)
```

4. Start development server:
```bash
task serve
```

Application will be available at http://localhost:8080

### Available Taskfile Commands:

```bash
# Environment management
task init          # Initialize development environment
task up            # Start Docker containers (PHP 8.2 + 8.4)
task down          # Stop Docker containers
task restart       # Restart environment
task logs          # Show logs

# Development
task serve         # Start dev server
task shell         # Shell into PHP 8.2 container
task shell84       # Shell into PHP 8.4 container

# Testing
task test          # Run PHPUnit tests (PHP 8.2)
task test:84       # Run PHPUnit tests (PHP 8.4)
task test:coverage # Run tests with coverage report

# Symfony Version Matrix
task test:sf64     # Test with Symfony 6.4 (PHP 8.2)
task test:sf71     # Test with Symfony 7.1 (PHP 8.2)
task test:sf80     # Test with Symfony 8.0 (PHP 8.4)
task test:matrix   # Run full test matrix

# Code Quality
task stan          # PHPStan analysis
task cs            # Check code style
task cs:fix        # Fix code style
```

### Endpoints:
- `POST /demo/fire-and-forget` - Fire & Forget test
- `POST /demo/with-callback` - Async callback test
- `POST /demo/sync` - Synchronous test
- `GET /demo/health` - Health check
- `POST /api/n8n/callback` - Callback endpoint
- `GET /_profiler` - Symfony Web Profiler

## Example Usage

### Forum Post Moderation

```php
// 1. Post implements N8nPayloadInterface
class ForumPost implements N8nPayloadInterface
{
    public function toN8nPayload(): array
    {
        return [
            'text' => $this->content,
            'author_id' => $this->authorId,
            'thread_id' => $this->threadId
        ];
    }
}

// 2. Handler for processing results
class ForumPostModerationHandler implements N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        $status = $responseData['status']; // 'ok', 'suspicious', 'blocked'
        $spamScore = $responseData['spam_score'];
        $flags = $responseData['flags'];
        
        // Based on result: publish/block/send for manual review
        match($responseData['suggested_action']) {
            'approve' => $this->approvePost($postId),
            'manual_review' => $this->queueForManualReview($postId, $flags),
            'block' => $this->blockPost($postId, $flags)
        };
    }
}

// 3. Usage
$post = new ForumPost(/*...*/);
$handler = new ForumPostModerationHandler();

$uuid = $n8nClient->sendWithCallback($post, 'moderation-workflow-id', $handler);
```

## Testing

### Mock Client

For testing your application without making actual HTTP requests to n8n, use the `MockN8nClient`:

```php
<?php

use Freema\N8nBundle\Testing\MockN8nClient;

class MyServiceTest extends TestCase
{
    private MockN8nClient $mockN8n;

    protected function setUp(): void
    {
        $this->mockN8n = new MockN8nClient();
    }

    public function testForumPostModeration(): void
    {
        // Configure the mock response
        $this->mockN8n->willReturn([
            'status' => 'approved',
            'spam_score' => 0.1,
            'confidence' => 'high'
        ]);

        // Test your service
        $service = new ModerationService($this->mockN8n);
        $result = $service->moderatePost($forumPost);

        // Verify the request was sent
        $this->mockN8n->assertSent('moderation-workflow-id');
        $this->mockN8n->assertSentCount(1);

        // Verify payload content
        $this->mockN8n->assertSentWithPayload('moderation-workflow-id', [
            'text' => 'Forum post content'
        ]);
    }
}
```

#### Mock Client Features

**Configure responses:**
```php
// Single response
$mockClient->willReturn(['status' => 'ok']);

// Multiple responses in sequence
$mockClient->willReturnSequence([
    ['status' => 'pending'],
    ['status' => 'completed'],
]);

// Simulate exceptions
$mockClient->willThrow(new N8nCommunicationException('Connection failed', 500));
```

**Assertions:**
```php
// Assert request was sent
$mockClient->assertSent('workflow-id');

// Assert with custom callback
$mockClient->assertSent('workflow-id', function (array $request) {
    return $request['payload']->getSomeValue() === 'expected';
});

// Assert request was not sent
$mockClient->assertNotSent('workflow-id');

// Assert number of requests
$mockClient->assertSentCount(3);

// Assert nothing was sent
$mockClient->assertNothingSent();

// Assert payload data
$mockClient->assertSentWithPayload('workflow-id', [
    'key' => 'expected-value'
]);
```

**Inspect requests:**
```php
// Get all requests
$requests = $mockClient->getRequests();

// Get requests for specific workflow
$requests = $mockClient->getRequestsFor('workflow-id');

// Reset state between tests
$mockClient->reset();
```

**Configure behavior:**
```php
// Custom client ID
$mockClient->withClientId('test-client');

// Health status
$mockClient->withHealthStatus(false);
```

## Configuration

### Complete Configuration

```yaml
n8n:
  clients:
    default:
      base_url: 'https://n8n.example.com'
      client_id: 'my-app'
      auth_token: '%env(N8N_AUTH_TOKEN)%'
      timeout_seconds: 30
      retry_attempts: 3
      retry_delay_ms: 1000
      enable_circuit_breaker: true
      circuit_breaker_threshold: 5
      circuit_breaker_timeout_seconds: 60
      dry_run: false
      use_test_webhook: false  # Use /webhook-test/ for unpublished workflows
      proxy: 'http://proxy.example.com:3128'  # Optional HTTP proxy
      default_headers:
        X-Custom-Header: 'My App'
    
    staging:
      base_url: 'https://staging.n8n.example.com'
      client_id: 'my-app-staging'
      dry_run: true
  
  callback:
    route_name: 'n8n_callback'
    route_path: '/api/n8n/callback'
  
  tracking:
    cleanup_interval_seconds: 3600
    max_request_age_seconds: 86400
```

## Author

Created by [Tomáš Grasl](https://www.tomasgrasl.cz/)

## License

MIT