<?php

namespace Tests\Unit\Services\Import;

use App\Exceptions\Import\ImportException;
use App\Services\Import\ImportParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportParserTest extends TestCase
{
    #[Test]
    public function it_normalizes_fields_and_options(): void
    {
        $result = (new ImportParser)->normalize([
            'title' => 'Survey',
            'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => 'Yes'],
                ['label' => 'Department', 'type' => 'select', 'options' => 'HR, Sales'],
                ['label' => 'Skip Me', 'type' => 'magic'],
            ],
        ]);

        $this->assertSame('Survey', $result['title']);
        $this->assertCount(2, $result['fields']);
        $this->assertTrue($result['fields'][0]['is_required']);
        $this->assertSame(['HR', 'Sales'], $result['fields'][1]['field_options']);
    }

    #[Test]
    public function it_normalizes_duplicate_names(): void
    {
        $result = (new ImportParser)->normalize([
            'title' => 'Form',
            'fields' => [
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Email', 'type' => 'email'],
            ],
        ]);

        $this->assertSame('email', $result['fields'][0]['name']);
        $this->assertSame('email_1', $result['fields'][1]['name']);
    }

    #[Test]
    public function it_rejects_empty_documents(): void
    {
        $this->expectException(ImportException::class);

        (new ImportParser)->normalize([
            'title' => 'Form',
            'fields' => [
                ['label' => 'Weird', 'type' => 'not-a-type'],
            ],
        ]);
    }
}
