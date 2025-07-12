# Symfony N8n Bundle

Elegantní integrace mezi Symfony aplikacemi a n8n workflow automation platformou.

## Funkce

- **Type-safe komunikace** pomocí PHP interfaces
- **UUID tracking systém** pro párování request/response
- **Flexibilní komunikační módy**: Fire & Forget, Async s callbackem, Sync
- **Robustní error handling** s retry a circuit breaker
- **Event-driven architektura** pro monitoring a logging
- **Multi-instance podpora** pro různá prostředí
- **Dry run mode** pro testování bez skutečného odeslání

## Rychlý start

### 1. Instalace

```bash
composer require freema/n8n-bundle
```

### 2. Development s Docker a Taskfile

```bash
# Instalace Task (https://taskfile.dev)
brew install go-task/tap/go-task

# Inicializace vývojového prostředí
task init

# Spuštění dev serveru
task dev:serve

# Zobrazení dostupných příkazů
task --list
```

### 3. Konfigurace

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
```

### 4. Implementace entity

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

    // Volitelné: definuj HTTP metodu a content type
    public function getN8nRequestMethod(): RequestMethod
    {
        return RequestMethod::POST_FORM; // nebo POST_JSON, GET, atd.
    }

    // Volitelné: vlastní response handler
    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface
    {
        return new ModerationResponseHandler();
    }

    // Volitelné: mapování response na entitu
    public function getN8nResponseClass(): ?string
    {
        return ModerationResponse::class;
    }
}
```

### 5. Použití

```php
<?php

// Fire & Forget - vrací response data ihned
$result = $n8nClient->send($post, 'workflow-id');
// $result = ['uuid' => '...', 'response' => [...], 'mapped_response' => object, 'status_code' => 200]

// Async s callback
$uuid = $n8nClient->sendWithCallback($post, 'workflow-id', $responseHandler);

// Sync
$result = $n8nClient->sendSync($post, 'workflow-id');
```

## Komunikační módy

### Fire & Forget
Pošle data do n8n a vrátí okamžitou odpověď z webhooku.

```php
$result = $n8nClient->send($payload, 'workflow-id');
// Vrací: ['uuid' => '...', 'response' => {...}, 'mapped_response' => object|null, 'status_code' => 200]
```

### Async s callbackem
Pošle data + callback URL, n8n zpracuje a vrátí výsledek.

```php
class MyResponseHandler implements N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        // Zpracuj odpověď z n8n
    }
    
    public function getHandlerId(): string
    {
        return 'my_handler';
    }
}

$uuid = $n8nClient->sendWithCallback($payload, 'workflow-id', new MyResponseHandler());
```

### Sync
Čeká na okamžitou odpověď (pokud n8n webhook podporuje).

```php
$result = $n8nClient->sendSync($payload, 'workflow-id', 30); // 30s timeout
```

## HTTP metody a content typy

Bundle podporuje různé HTTP metody a content typy:

```php
use Freema\N8nBundle\Enum\RequestMethod;

class MyPayload implements N8nPayloadInterface
{
    public function getN8nRequestMethod(): RequestMethod
    {
        return RequestMethod::POST_FORM;  // Form data (application/x-www-form-urlencoded)
        // return RequestMethod::POST_JSON;  // JSON body (application/json)
        // return RequestMethod::GET;        // GET parametry
        // return RequestMethod::PUT_JSON;   // PUT s JSON
        // return RequestMethod::PATCH_FORM; // PATCH s form data
    }
}
```

## Response mapování na entity

Můžeš automaticky mapovat n8n response na PHP objekty:

```php
// 1. Vytvoř response entitu
class ModerationResponse
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $confidence = null
    ) {}
}

// 2. Specifikuj třídu v payload
class ForumPost implements N8nPayloadInterface
{
    public function getN8nResponseClass(): ?string
    {
        return ModerationResponse::class;
    }
}

// 3. Použij namapovaný objekt
$result = $n8nClient->send($post, 'workflow-id');
$mappedResponse = $result['mapped_response']; // Instance ModerationResponse
$isAllowed = $mappedResponse->allowed; // Type-safe přístup
```

## Debug panel

Bundle obsahuje debug panel pro Symfony Web Profiler:

```yaml
# config/packages/n8n.yaml
n8n:
  debug:
    enabled: true  # nebo null pro auto-detekci podle kernel.debug
    collect_requests: true
    log_requests: true
```

Panel zobrazuje:
- Všechny N8n requesty s UUID, duration, status
- Payload data a response data  
- Chyby a jejich detaily
- Celkový počet requestů a čas

## Monitoring a eventy

Bundle emituje eventy pro každou fázi komunikace:

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

## Testovací aplikace

V adresáři `dev/` je připravena testovací aplikace s Docker podporou:

### Setup

1. Spuštění Docker kontejneru:
```bash
task up
```

2. Instalace závislostí:
```bash
task init
```

3. Konfigurace prostředí:
```bash
# Zkopírujte vzorový soubor
cp dev/.env.example dev/.env.local

# Upravte dev/.env.local a vyplňte:
# - N8N_WEBHOOK_FIRE_AND_FORGET - váš webhook ID
# - N8N_WEBHOOK_WITH_CALLBACK - váš webhook ID pro callback
# - N8N_CALLBACK_BASE_URL - URL kde běží vaše aplikace (pro callback)
```

4. Spuštění vývojového serveru:
```bash
task dev:serve
```

Aplikace bude dostupná na http://localhost:8080

### Dostupné Taskfile příkazy:

```bash
# Správa prostředí
task init          # Inicializace vývojového prostředí
task up            # Spuštění Docker kontejnerů
task down          # Zastavení Docker kontejnerů
task restart       # Restart prostředí
task logs          # Zobrazení logů

# Development
task dev:serve     # Spuštění dev serveru
task dev:shell     # Shell do dev kontejneru
task php:shell     # Shell do PHP kontejneru
task demo          # Zobrazení demo endpointů

# N8n testování
task n8n:ff        # Test Fire & Forget
task n8n:cb        # Test Callback
task n8n:health    # Health check
task n8n:clean     # Cleanup příkaz

# Testování
task test          # Spuštění PHPUnit testů
task test:all      # Test všech Symfony verzí
task stan          # PHPStan analýza
task cs:fix        # Oprava code style
```

### Endpointy:
- `POST /demo/fire-and-forget` - Fire & Forget test
- `POST /demo/with-callback` - Async callback test
- `POST /demo/sync` - Synchronní test
- `GET /demo/health` - Health check
- `POST /api/n8n/callback` - Callback endpoint
- `GET /_profiler` - Symfony Web Profiler

## Příklad použití

### Kontrola příspěvků v diskuzích

```php
// 1. Příspěvek implementuje N8nPayloadInterface
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

// 2. Handler pro zpracování výsledku
class ForumPostModerationHandler implements N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        $status = $responseData['status']; // 'ok', 'suspicious', 'blocked'
        $spamScore = $responseData['spam_score'];
        $flags = $responseData['flags'];
        
        // Podle výsledku publikuj/blokuj/pošli k manuální kontrole
        match($responseData['suggested_action']) {
            'approve' => $this->approvePost($postId),
            'manual_review' => $this->queueForManualReview($postId, $flags),
            'block' => $this->blockPost($postId, $flags)
        };
    }
}

// 3. Použití
$post = new ForumPost(/*...*/);
$handler = new ForumPostModerationHandler();

$uuid = $n8nClient->sendWithCallback($post, 'moderation-workflow-id', $handler);
```

## Konfigurace

### Kompletní konfigurace

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

## Licencia

MIT