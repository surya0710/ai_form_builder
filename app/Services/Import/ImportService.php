<?php

namespace App\Services\Import;

use App\Enums\ImportStatus;
use App\Exceptions\Import\ImportException;
use App\Models\Form;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\Form\FormBuilderService;
use App\Services\Form\FormService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportService
{
    public function __construct(
        protected WordImportService $wordImport,
        protected ExcelImportService $excelImport,
        protected ImportParser $parser,
        protected FormService $formService,
        protected FormBuilderService $formBuilderService,
    ) {}

    /**
     * Parse and normalize an uploaded file without persisting a form.
     *
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>, file_type: string}
     *
     * @throws ImportException
     */
    public function preview(UploadedFile $file): array
    {
        $extension = $this->detectExtension($file);
        $parsed = $this->parseFile($file, $extension);
        $normalized = $this->parser->normalize($parsed);
        $normalized['file_type'] = $extension;

        return $normalized;
    }

    /**
     * Import an uploaded document into a persisted form.
     *
     * @throws ImportException|Throwable
     */
    public function import(User $user, UploadedFile $file): Form
    {
        $extension = $this->detectExtension($file);

        $log = ImportLog::query()->create([
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $extension,
            'status' => ImportStatus::Processing,
            'imported_forms' => 0,
        ]);

        try {
            $parsed = $this->parseFile($file, $extension);
            $normalized = $this->parser->normalize($parsed);

            $form = DB::transaction(function () use ($user, $normalized): Form {
                $form = $this->formService->create($user, [
                    'title' => $normalized['title'],
                    'description' => $normalized['description'] ?? null,
                ]);

                foreach ($normalized['fields'] as $field) {
                    $this->formBuilderService->addField($form, $field);
                }

                return $form;
            });

            $log->update([
                'status' => ImportStatus::Completed,
                'imported_forms' => 1,
                'error_log' => null,
                'completed_at' => now(),
            ]);

            return $form->load('fields');
        } catch (Throwable $e) {
            $log->update([
                'status' => ImportStatus::Failed,
                'imported_forms' => 0,
                'error_log' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            if (! $e instanceof ImportException) {
                Log::error('Form import failed', [
                    'user_id' => $user->id,
                    'log_id' => $log->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Persist a previously previewed/normalized structure.
     *
     * @param  array{title: string, description?: string|null, fields: array<int, array<string, mixed>>}  $normalized
     *
     * @throws ImportException|Throwable
     */
    public function importNormalized(User $user, array $normalized, string $fileName, string $fileType): Form
    {
        $log = ImportLog::query()->create([
            'user_id' => $user->id,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'status' => ImportStatus::Processing,
            'imported_forms' => 0,
        ]);

        try {
            $normalized = $this->parser->normalize($normalized);

            $form = DB::transaction(function () use ($user, $normalized): Form {
                $form = $this->formService->create($user, [
                    'title' => $normalized['title'],
                    'description' => $normalized['description'] ?? null,
                ]);

                foreach ($normalized['fields'] as $field) {
                    $this->formBuilderService->addField($form, $field);
                }

                return $form;
            });

            $log->update([
                'status' => ImportStatus::Completed,
                'imported_forms' => 1,
                'completed_at' => now(),
            ]);

            return $form->load('fields');
        } catch (Throwable $e) {
            $log->update([
                'status' => ImportStatus::Failed,
                'error_log' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws ImportException
     */
    protected function parseFile(UploadedFile $file, string $extension): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ImportException::corruptDocument('Unable to access uploaded file path.');
        }

        return match ($extension) {
            'docx' => $this->wordImport->parse($path),
            'xlsx' => $this->excelImport->parse($path),
            default => throw ImportException::unsupportedFileType($extension),
        };
    }

    /**
     * @throws ImportException
     */
    protected function detectExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        $allowed = config('forms.import.allowed_extensions', ['docx', 'xlsx']);

        if (! in_array($extension, $allowed, true)) {
            throw ImportException::unsupportedFileType($extension !== '' ? $extension : 'unknown');
        }

        return $extension;
    }
}
