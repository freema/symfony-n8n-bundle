<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev\Controller;

use Freema\N8nBundle\Dev\Entity\ForumPost;
use Freema\N8nBundle\Dev\Service\ForumPostModerationHandler;
use Freema\N8nBundle\Contract\N8nClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DemoController extends AbstractController
{
    public function __construct(
        private readonly N8nClientInterface $n8nClient,
        private readonly ForumPostModerationHandler $moderationHandler
    ) {}

    public function fireAndForget(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        $post = new ForumPost(
            id: 1,
            content: $data['text'] ?? 'Hello from Symfony!',
            authorId: 123,
            createdAt: new \DateTimeImmutable(),
            threadId: 'thread-456'
        );

        $uuid = $this->n8nClient->send($post, $_ENV['N8N_WEBHOOK_FIRE_AND_FORGET']);

        return new JsonResponse([
            'status' => 'sent',
            'uuid' => $uuid,
            'mode' => 'fire_and_forget'
        ]);
    }

    public function withCallback(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        $post = new ForumPost(
            id: 2,
            content: $data['text'] ?? 'Please check this message for moderation',
            authorId: 456,
            createdAt: new \DateTimeImmutable(),
            threadId: 'thread-789'
        );

        $uuid = $this->n8nClient->sendWithCallback(
            $post,
            $_ENV['N8N_WEBHOOK_WITH_CALLBACK'],
            $this->moderationHandler
        );

        return new JsonResponse([
            'status' => 'sent',
            'uuid' => $uuid,
            'mode' => 'async_with_callback'
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        $post = new ForumPost(
            id: 3,
            content: $data['text'] ?? 'Test synchronous message',
            authorId: 789,
            createdAt: new \DateTimeImmutable(),
            threadId: 'thread-sync'
        );

        try {
            $result = $this->n8nClient->sendSync($post, $_ENV['N8N_WEBHOOK_SYNC'] ?? 'sync-workflow-id');

            return new JsonResponse([
                'status' => 'success',
                'result' => $result,
                'mode' => 'sync'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'mode' => 'sync'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function health(): JsonResponse
    {
        $isHealthy = $this->n8nClient->isHealthy();

        return new JsonResponse([
            'n8n_healthy' => $isHealthy,
            'client_id' => $this->n8nClient->getClientId()
        ]);
    }

    public function index(): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>N8n Bundle Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .endpoint { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #007bff; }
        .method { font-weight: bold; color: #28a745; }
        .path { color: #333; font-family: monospace; }
        .description { color: #666; font-size: 14px; margin-top: 5px; }
        .test-section { margin-top: 30px; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .response { background: #f8f9fa; padding: 15px; margin-top: 15px; border-radius: 4px; display: none; }
        .response.success { border-left: 4px solid #28a745; }
        .response.error { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 N8n Bundle Demo Application</h1>
        <p>Welcome to the N8n Bundle demo application. This bundle provides elegant integration between Symfony and n8n workflow automation platform.</p>
        
        <h2>Available Endpoints</h2>
        
        <div class="endpoint">
            <span class="method">POST</span> <span class="path">/demo/fire-and-forget</span>
            <div class="description">Send a fire-and-forget request to n8n. The request is sent but we don't wait for or expect a response.</div>
        </div>
        
        <div class="endpoint">
            <span class="method">POST</span> <span class="path">/demo/with-callback</span>
            <div class="description">Send a request with callback handler. N8n will process the request and send the result back to our callback endpoint.</div>
        </div>
        
        <div class="endpoint">
            <span class="method">POST</span> <span class="path">/demo/sync</span>
            <div class="description">Send a synchronous request. We wait for n8n to process and return the result immediately.</div>
        </div>
        
        <div class="endpoint">
            <span class="method">GET</span> <span class="path">/demo/health</span>
            <div class="description">Check the health status of the n8n connection.</div>
        </div>
        
        <div class="endpoint">
            <span class="method">POST</span> <span class="path">/api/n8n/callback</span>
            <div class="description">Callback endpoint that n8n uses to send responses back to our application.</div>
        </div>
        
        <div class="test-section">
            <h2>Test Fire & Forget</h2>
            <textarea id="fireForgetData">{"text": "Hello from Symfony! This is a test message."}</textarea>
            <button onclick="testFireAndForget()">Send Fire & Forget</button>
            <div id="fireForgetResponse" class="response"></div>
        </div>
        
        <div class="test-section">
            <h2>Test Callback</h2>
            <textarea id="callbackData">{"text": "Please review this content for moderation."}</textarea>
            <button onclick="testCallback()">Send with Callback</button>
            <div id="callbackResponse" class="response"></div>
        </div>
        
        <div class="test-section">
            <h2>Test Health Check</h2>
            <button onclick="testHealth()">Check Health</button>
            <div id="healthResponse" class="response"></div>
        </div>
    </div>
    
    <script>
        function testFireAndForget() {
            fetch('/demo/fire-and-forget', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: document.getElementById('fireForgetData').value
            })
            .then(response => response.json())
            .then(data => {
                const div = document.getElementById('fireForgetResponse');
                div.className = 'response success';
                div.style.display = 'block';
                div.innerHTML = '<strong>Success!</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                const div = document.getElementById('fireForgetResponse');
                div.className = 'response error';
                div.style.display = 'block';
                div.innerHTML = '<strong>Error!</strong><pre>' + error + '</pre>';
            });
        }
        
        function testCallback() {
            fetch('/demo/with-callback', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: document.getElementById('callbackData').value
            })
            .then(response => response.json())
            .then(data => {
                const div = document.getElementById('callbackResponse');
                div.className = 'response success';
                div.style.display = 'block';
                div.innerHTML = '<strong>Success!</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                const div = document.getElementById('callbackResponse');
                div.className = 'response error';
                div.style.display = 'block';
                div.innerHTML = '<strong>Error!</strong><pre>' + error + '</pre>';
            });
        }
        
        function testHealth() {
            fetch('/demo/health')
            .then(response => response.json())
            .then(data => {
                const div = document.getElementById('healthResponse');
                div.className = 'response success';
                div.style.display = 'block';
                div.innerHTML = '<strong>Health Check:</strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                const div = document.getElementById('healthResponse');
                div.className = 'response error';
                div.style.display = 'block';
                div.innerHTML = '<strong>Error!</strong><pre>' + error + '</pre>';
            });
        }
    </script>
</body>
</html>
HTML;

        return new Response($html);
    }
}