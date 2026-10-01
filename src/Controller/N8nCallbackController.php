<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Controller;

use Freema\N8nBundle\Domain\N8nResponse;
use Freema\N8nBundle\Service\CallbackHandler;
use Freema\N8nBundle\Service\CallbackSigner;
use Freema\N8nBundle\Service\RequestTracker;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class N8nCallbackController extends AbstractController
{
    public function __construct(
        private readonly CallbackHandler $callbackHandler,
        private readonly LoggerInterface $logger,
        private readonly ?CallbackSigner $callbackSigner = null,
        private readonly ?RequestTracker $requestTracker = null,
        private readonly bool $requireSignature = true,
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
                // The caller is not authenticated yet: log the event, not the body.
                $this->logger->error('N8n callback received invalid JSON', [
                    'error' => json_last_error_msg(),
                    'length' => \strlen($payload),
                ]);

                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            if (!\is_array($data)) {
                $this->logger->error('N8n callback data is not an array', ['type' => get_debug_type($data)]);

                return new JsonResponse(['error' => 'Invalid data format'], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['_n8n_bundle']) || !\is_array($data['_n8n_bundle']) || !isset($data['_n8n_bundle']['uuid']) || !\is_string($data['_n8n_bundle']['uuid'])) {
                $this->logger->error('N8n callback missing required UUID');

                return new JsonResponse(['error' => 'Missing UUID'], Response::HTTP_BAD_REQUEST);
            }

            $uuid = $data['_n8n_bundle']['uuid'];

            if ($this->requireSignature && !$this->hasValidSignature($request, $data['_n8n_bundle'], $uuid)) {
                $this->logger->warning('N8n callback rejected: invalid or missing signature', [
                    'uuid' => $uuid,
                    'client_ip' => $request->getClientIp(),
                ]);

                return new JsonResponse(['error' => 'Invalid or missing callback signature'], Response::HTTP_UNAUTHORIZED);
            }

            if ($this->requestTracker?->isCompleted($uuid)) {
                $this->logger->warning('N8n callback rejected: already handled', ['uuid' => $uuid]);

                return new JsonResponse(['error' => 'Callback already handled'], Response::HTTP_CONFLICT);
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

    /**
     * The signature comes on the callback URL sendWithCallback() handed to
     * n8n, or inside the echoed "_n8n_bundle" block of the body.
     *
     * @param array<mixed> $bundleData
     */
    private function hasValidSignature(Request $request, array $bundleData, string $uuid): bool
    {
        if ($this->callbackSigner === null) {
            return false;
        }

        $signature = $request->query->get(CallbackSigner::SIGNATURE_PARAMETER);
        $expires = $request->query->get(CallbackSigner::EXPIRES_PARAMETER);

        if ($signature === null) {
            $signature = $bundleData[CallbackSigner::SIGNATURE_PARAMETER] ?? null;
            $expires = $bundleData[CallbackSigner::EXPIRES_PARAMETER] ?? null;
        }

        return $this->callbackSigner->verify($uuid, $expires, $signature);
    }
}
