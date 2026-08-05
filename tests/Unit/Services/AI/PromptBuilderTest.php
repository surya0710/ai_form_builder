<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\PromptBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_deterministic_prompt_with_constraints(): void
    {
        $prompt = (new PromptBuilder)->build('Make a contact form');

        $this->assertStringContainsString('Make a contact form', $prompt);
        $this->assertStringContainsString('Supported field types:', $prompt);
        $this->assertStringContainsString('Required JSON schema:', $prompt);
        $this->assertStringContainsString('Return JSON only.', $prompt);
        $this->assertStringContainsString('Do not wrap the JSON in markdown code blocks.', $prompt);
        $this->assertStringContainsString('Do not include explanations', $prompt);
    }
}
