<?php

namespace Tests\Feature\Api;

use App\Enums\ImportStatus;
use App\Models\User;
use App\Services\Import\ExcelImportService;
use App\Services\Import\WordImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class ImportFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_can_import_docx_via_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(WordImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->once()->andReturn([
                'title' => 'Employee Feedback Form',
                'description' => null,
                'fields' => [
                    ['label' => 'Name', 'type' => 'text', 'required' => true],
                    ['label' => 'Department', 'type' => 'select', 'options' => ['HR', 'Sales', 'Engineering']],
                    ['label' => 'Email', 'type' => 'email'],
                ],
            ]);
        });

        $file = UploadedFile::fake()->create(
            'feedback.docx',
            20,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Employee Feedback Form')
            ->assertJsonCount(3, 'data.fields');

        $this->assertDatabaseHas('forms', [
            'user_id' => $user->id,
            'title' => 'Employee Feedback Form',
        ]);

        $this->assertDatabaseHas('form_fields', [
            'label' => 'Department',
            'type' => 'select',
        ]);

        $this->assertDatabaseHas('import_logs', [
            'user_id' => $user->id,
            'file_name' => 'feedback.docx',
            'file_type' => 'docx',
            'status' => ImportStatus::Completed->value,
            'imported_forms' => 1,
        ]);
    }

    public function test_can_import_xlsx_via_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(ExcelImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->once()->andReturn([
                'title' => 'Survey Form',
                'description' => null,
                'fields' => [
                    ['label' => 'Name', 'type' => 'text', 'required' => 'Yes'],
                    ['label' => 'Department', 'type' => 'select', 'required' => 'Yes', 'options' => 'HR, Sales, Engineering'],
                    ['label' => 'Rating', 'type' => 'number', 'required' => 'No'],
                ],
            ]);
        });

        $file = UploadedFile::fake()->create(
            'survey.xlsx',
            20,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Survey Form')
            ->assertJsonCount(3, 'data.fields');

        $field = $user->forms()->first()->fields()->where('label', 'Department')->first();
        $this->assertSame(['HR', 'Sales', 'Engineering'], $field->field_options);

        $this->assertDatabaseHas('import_logs', [
            'file_type' => 'xlsx',
            'status' => ImportStatus::Completed->value,
        ]);
    }

    public function test_rejects_unsupported_file_type(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rejects_empty_parsed_document(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(WordImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->once()->andReturn([
                'title' => 'Empty Form',
                'description' => null,
                'fields' => [],
            ]);
        });

        $file = UploadedFile::fake()->create(
            'empty.docx',
            10,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('import_logs', [
            'user_id' => $user->id,
            'status' => ImportStatus::Failed->value,
        ]);

        $this->assertDatabaseCount('forms', 0);
    }

    public function test_rejects_invalid_structure(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(ExcelImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->once()->andReturn([
                'title' => '',
                'fields' => [
                    ['label' => 'Name', 'type' => 'text'],
                ],
            ]);
        });

        $file = UploadedFile::fake()->create(
            'bad.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('import_logs', ['status' => ImportStatus::Failed->value]);
    }

    public function test_normalizes_duplicate_field_names(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(WordImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->once()->andReturn([
                'title' => 'Duplicates',
                'fields' => [
                    ['label' => 'Email', 'type' => 'email'],
                    ['label' => 'Email', 'type' => 'email'],
                ],
            ]);
        });

        $file = UploadedFile::fake()->create(
            'dupes.docx',
            10,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('form_fields', ['name' => 'email']);
        $this->assertDatabaseHas('form_fields', ['name' => 'email_1']);
    }

    public function test_ignores_unsupported_field_types(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->mock(ExcelImportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')->once()->andReturn([
                'title' => 'Mixed',
                'fields' => [
                    ['label' => 'Name', 'type' => 'text'],
                    ['label' => 'Weird', 'type' => 'magic'],
                ],
            ]);
        });

        $file = UploadedFile::fake()->create(
            'mixed.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->withToken($token)->post('/api/v1/forms/import', [
            'file' => $file,
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.fields');
        $this->assertDatabaseMissing('form_fields', ['label' => 'Weird']);
    }

    public function test_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create(
            'feedback.docx',
            10,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $this->post('/api/v1/forms/import', ['file' => $file])
            ->assertUnauthorized();
    }

    public function test_validates_missing_file(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/forms/import', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_word_service_parses_real_docx(): void
    {
        $path = $this->makeDocx([
            'Employee Feedback Form',
            'Name',
            'Email',
            'Department',
            '- HR',
            '- Sales',
            '- Engineering',
            'Comments',
        ]);

        $parsed = app(WordImportService::class)->parse($path);

        $this->assertSame('Employee Feedback Form', $parsed['title']);
        $this->assertSame('Name', $parsed['fields'][0]['label']);
        $this->assertSame('email', $parsed['fields'][1]['type']);
        $this->assertSame('select', $parsed['fields'][2]['type']);
        $this->assertSame(['HR', 'Sales', 'Engineering'], $parsed['fields'][2]['options']);
        $this->assertSame('textarea', $parsed['fields'][3]['type']);
    }

    public function test_excel_service_parses_real_xlsx(): void
    {
        $path = $this->makeXlsx(
            'Contact Form',
            [
                ['Label', 'Type', 'Required', 'Options', 'Notes'],
                ['Name', 'text', 'Yes', '', 'ignored'],
                ['Department', 'select', 'Yes', 'HR, Sales, Engineering', ''],
                ['Rating', 'number', 'No', '', ''],
            ]
        );

        $parsed = app(ExcelImportService::class)->parse($path);

        $this->assertSame('Contact Form', $parsed['title']);
        $this->assertCount(3, $parsed['fields']);
        $this->assertSame('Department', $parsed['fields'][1]['label']);
        $this->assertSame('HR, Sales, Engineering', $parsed['fields'][1]['options']);
    }

    /**
     * @param  list<string>  $lines
     */
    private function makeDocx(array $lines): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        foreach ($lines as $line) {
            $section->addText($line);
        }

        $path = storage_path('framework/testing-'.uniqid('docx_', true).'.docx');
        WordIOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $this->beforeApplicationDestroyed(static function () use ($path): void {
            if (is_file($path)) {
                @unlink($path);
            }
        });

        return $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function makeXlsx(string $title, array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        $sheet->setCellValue('A1', $title);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex + 2], $value);
            }
        }

        $path = storage_path('framework/testing-'.uniqid('xlsx_', true).'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        $this->beforeApplicationDestroyed(static function () use ($path): void {
            if (is_file($path)) {
                @unlink($path);
            }
        });

        return $path;
    }
}
