<?php

namespace App\Exceptions\AI;

use Throwable;

class AIProviderException extends AIException
{
    public static function missingApiKey(): self
    {
        return new self(
            'AI provider API key is missing.',
            'AI generation is not configured. Please contact the administrator.',
            503,
        );
    }

    public static function unauthorized(string $detail): self
    {
        return new self(
            'AI provider authentication failed: '.$detail,
            'AI provider authentication failed.',
            502,
        );
    }

    public static function rateLimited(string $detail): self
    {
        return new self(
            'AI provider rate limit exceeded: '.$detail,
            'AI provider is rate limited. Please try again shortly.',
            429,
        );
    }

    public static function timeout(?Throwable $previous = null): self
    {
        return new self(
            'AI provider request timed out.',
            'AI provider request timed out. Please try again.',
            504,
            $previous,
        );
    }

    public static function unavailable(string $detail, int $status = 502): self
    {
        return new self(
            'AI provider unavailable: '.$detail,
            'AI provider is currently unavailable. Please try again later.',
            $status,
        );
    }
}
