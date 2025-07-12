<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Enum;

enum RequestMethod: string
{
    case GET = 'GET';
    case POST_JSON = 'POST_JSON';
    case POST_FORM = 'POST_FORM';
    case PUT_JSON = 'PUT_JSON';
    case PUT_FORM = 'PUT_FORM';
    case PATCH_JSON = 'PATCH_JSON';
    case PATCH_FORM = 'PATCH_FORM';

    public function getHttpMethod(): string
    {
        return match($this) {
            self::GET => 'GET',
            self::POST_JSON, self::POST_FORM => 'POST',
            self::PUT_JSON, self::PUT_FORM => 'PUT',
            self::PATCH_JSON, self::PATCH_FORM => 'PATCH',
        };
    }

    public function getContentType(): ?string
    {
        return match($this) {
            self::GET => null,
            self::POST_JSON, self::PUT_JSON, self::PATCH_JSON => 'application/json',
            self::POST_FORM, self::PUT_FORM, self::PATCH_FORM => 'application/x-www-form-urlencoded',
        };
    }

    public function isFormData(): bool
    {
        return in_array($this, [
            self::POST_FORM,
            self::PUT_FORM,
            self::PATCH_FORM
        ], true);
    }

    public function isJsonData(): bool
    {
        return in_array($this, [
            self::POST_JSON,
            self::PUT_JSON,
            self::PATCH_JSON
        ], true);
    }
}