<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Controller;

use Freema\N8nBundle\Domain\N8nResponse;
use Freema\N8nBundle\Service\CallbackHandler;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class N8nCallbackController extends AbstractController
{
    public function __construct(
        private readonly CallbackHandler $callbackHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/n8n/callback', name: 'n8n_callback', methods: ['POST'])]
    public function handleCallback(Request $request): Response
    {
        try {
            $payload = $request->getContent();

            if (empty($payload)) {
                $this->logger->warning('N8n callback received empty payload');

                return new JsonResponse(['error' => 'Empty payload'], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($payload, true);

            if (json_last_error() !== \JSON_ERROR_NONE) {
                $this->logger->error('N8n callback received invalid JSON', [
                    'error' => json_last_error_msg(),
                    'payload' => $payload,
                ]);

                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            if (!\is_array($data)) {
                $this->logger->error('N8n callback data is not an array', ['data' => $data]);

                return new JsonResponse(['error' => 'Invalid data format'], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['_n8n_bundle']) || !\is_array($data['_n8n_bundle']) || !isset($data['_n8n_bundle']['uuid'])) {
                $this->logger->error('N8n callback missing required UUID', ['data' => $data]);

                return new JsonResponse(['error' => 'Missing UUID'], Response::HTTP_BAD_REQUEST);
            }

            $response = N8nResponse::fromWebhookPayload($data);

            $this->callbackHandler->handle($response);

            return new JsonResponse(['status' => 'success']);
        } catch (\Throwable $e) {
            $this->logger->error('N8n callback processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                ['error' => 'Callback processing failed'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
