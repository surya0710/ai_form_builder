<?php

namespace App\Exceptions\AI;

use Exception;
use Throwable;

abstract class AIException extends Exception
{
    public function __construct(
        string $message,
        protected string $publicMessage,
        protected int $statusCode = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
