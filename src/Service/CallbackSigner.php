<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\Exception\ExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Signs and verifies the callback URL handed to n8n.
 *
 * sendWithCallback() gives n8n a callback URL carrying an expiry and an
 * HMAC over the request UUID and that expiry. Only the holder of the secret
 * can produce a valid pair, so the callback endpoint accepts responses for
 * requests this application actually sent, and only until they expire.
 *
 * Without an explicit secret the key is kernel.secret, read only when a
 * callback is signed or verified: an application with an empty APP_SECRET
 * keeps working for send() and sendSync(), and only callbacks are refused.
 */
final class CallbackSigner
{
    public const SIGNATURE_PARAMETER = 'signature';
    public const EXPIRES_PARAMETER = 'expires';

    public function __construct(
        private readonly ?string $secret,
        private readonly int $ttlSeconds = 86400,
        private readonly ?ContainerBagInterface $parameters = null,
        private readonly string $secretParameter = 'kernel.secret',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    /**
     * @return array{expires: int, signature: string}
     */
    public function sign(string $uuid, ?int $now = null): array
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('The n8n callback secret is empty. Set "n8n.callback.secret" or "framework.secret" so callback URLs can be signed.');
        }

        $expires = ($now ?? time()) + $this->ttlSeconds;

        return [
            self::EXPIRES_PARAMETER => $expires,
            self::SIGNATURE_PARAMETER => $this->hash($uuid, $expires),
        ];
    }

    public function verify(string $uuid, mixed $expires, mixed $signature, ?int $now = null): bool
    {
        if (!$this->isConfigured() || $uuid === '' || !\is_string($signature) || $signature === '') {
            return false;
        }

        if (\is_int($expires)) {
            $expires = (string) $expires;
        }

        if (!\is_string($expires) || !ctype_digit($expires) || (int) $expires < ($now ?? time())) {
            return false;
        }

        return hash_equals($this->hash($uuid, (int) $expires), $signature);
    }

    private function hash(string $uuid, int $expires): string
    {
        return hash_hmac('sha256', 'n8n-bundle-callback|'.$uuid.'|'.$expires, $this->key());
    }

    private function key(): string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }

        try {
            $value = $this->parameters?->get($this->secretParameter);
        } catch (ExceptionInterface|NotFoundExceptionInterface) {
            // Missing (Symfony 6.4 without framework.secret) or empty (7.2+).
            return '';
        }

        return \is_string($value) ? $value : '';
    }
}
