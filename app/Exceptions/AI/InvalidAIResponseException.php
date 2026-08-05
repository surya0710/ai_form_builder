<?php

namespace App\Exceptions\AI;

class InvalidAIResponseException extends AIException
{
    public static function invalidJson(string $detail = ''): self
    {
        return new self(
            trim('Invalid JSON response from AI provider. '.$detail),
            'The AI provider returned an invalid response. Please try again.',
            422,
        );
    }

    public static function unsupportedSchema(string $detail): self
    {
        return new self(
            'Unsupported AI response schema: '.$detail,
            'The AI provider returned an unsupported form schema. Please try again.',
            422,
        );
    }
}
