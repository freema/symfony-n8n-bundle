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

// Fire & Forget - returns response data immediately
$result = $n8nClient->send($post, 'workflow-id');
// $result = ['uuid' => '...', 'response' => [...], 'mapped_response' => object, 'status_code' => 200]

// Async with callback
$uuid = $n8nClient->sendWithCallback($post, 'workflow-id', $responseHandler);

// Sync
$result = $n8nClient->sendSync($post, 'workflow-id');
```

## Communication Modes

### Fire & Forget
Sends data to n8n and returns immediate response from webhook.

```php
$result = $n8nClient->send($payload, 'workflow-id');
// Returns: ['uuid' => '...', 'response' => {...}, 'mapped_response' => object|null, 'status_code' => 200]
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
$result = $n8nClient->sendSync($payload, 'workflow-id', 30); // 30s timeout
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
$result = $n8nClient->send($post, 'workflow-id');
$mappedResponse = $result['mapped_response']; // Instance of ModerationResponse
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

The `dev/` directory contains a test application with Docker support:

### Setup

1. Start Docker container:
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
task up            # Start Docker containers
task down          # Stop Docker containers
task restart       # Restart environment
task logs          # Show logs

# Development
task serve         # Start dev server
task shell         # Shell into dev container

# N8n testing
task test:health   # Health check

# Testing
task test          # Run PHPUnit tests
task stan          # PHPStan analysis
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

## License

MIT