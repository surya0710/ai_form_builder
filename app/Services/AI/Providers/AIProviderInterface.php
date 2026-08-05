<?php

namespace App\Services\AI\Providers;

interface AIProviderInterface
{
    /**
     * Generate a completion from the given prompt.
     *
     * @return array{content: string, tokens: int|null, model: string}
     */
    public function generate(string $prompt): array;
}
