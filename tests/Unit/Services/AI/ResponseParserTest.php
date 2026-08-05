<?php

namespace Tests\Unit\Services\AI;

use App\Exceptions\AI\InvalidAIResponseException;
use App\Services\AI\ResponseParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponseParserTest extends TestCase
{
    #[Test]
    public function it_parses_valid_payload(): void
    {
        $parser = new ResponseParser;

        $result = $parser->parse(json_encode([
            'title' => 'Feedback',
            'description' => 'Collect feedback',
            'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => true],
                ['label' => 'Rating', 'type' => 'radio', 'options' => ['Good', 'Bad']],
            ],
        ]));

        $this->assertSame('Feedback', $result['title']);
        $this->assertCount(2, $result['fields']);
        $this->assertTrue($result['fields'][0]['is_required']);
        $this->assertSame(['Good', 'Bad'], $result['fields'][1]['field_options']);
    }

    #[Test]
    public function it_throws_for_invalid_json(): void
    {
        $this->expectException(InvalidAIResponseException::class);

        (new ResponseParser)->parse('not-json');
    }

    #[Test]
    public function it_normalizes_duplicate_names(): void
    {
        $result = (new ResponseParser)->parse(json_encode([
            'title' => 'Form',
            'fields' => [
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Email', 'type' => 'email'],
            ],
        ]));

        $this->assertSame('email', $result['fields'][0]['name']);
        $this->assertSame('email_1', $result['fields'][1]['name']);
    }
}
