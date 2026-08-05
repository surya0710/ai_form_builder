<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIException;
use App\Exceptions\AI\InvalidAIResponseException;
use App\Models\AiGenerationLog;
use App\Models\Form;
use App\Models\User;
use App\Services\AI\Providers\AIProviderInterface;
use App\Services\Form\FormBuilderService;
use App\Services\Form\FormSchemaService;
use App\Services\Form\FormService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AIService
{
    public function __construct(
        protected AIProviderInterface $provider,
        protected PromptBuilder $promptBuilder,
        protected ResponseParser $parser,
        protected FormService $formService,
        protected FormBuilderService $formBuilderService,
        protected FormSchemaService $schemaService,
    ) {}

    /**
     * @throws AIException|Throwable
     */
    public function generateForm(User $user, string $prompt): Form
    {
        $parsedData = $this->generatePreview($user, $prompt);

        return $this->persistGeneratedForm($user, $parsedData);
    }

    /**
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws AIException|Throwable
     */
    public function generatePreview(User $user, string $prompt, ?AiGenerationLog $log = null): array
    {
        $builtPrompt = $this->promptBuilder->build($prompt);
        $providerName = (string) config('ai.default');
        $started = microtime(true);

        $log ??= AiGenerationLog::query()->create([
            'user_id' => $user->id,
            'prompt' => $prompt,
            'provider' => $providerName,
            'model' => config("ai.providers.{$providerName}.model"),
            'status' => 'generating',
            'mode' => 'generate',
            'generated_at' => now(),
        ]);

        $log->update(['status' => 'generating']);

        try {
            $response = $this->generateWithRetry($builtPrompt);
            $parsedData = $this->parser->parse($response['content']);

            $log->update([
                'response' => ['raw' => $response['content'], 'parsed' => $parsedData],
                'tokens' => $response['tokens'],
                'model' => $response['model'] ?? $log->model,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'status' => 'completed',
                'error_message' => null,
            ]);

            return $parsedData;
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            if (! $e instanceof AIException) {
                Log::error('AI form generation failed', [
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
     * @param  array{title: string, description?: string|null, fields: array<int, array<string, mixed>>}  $parsedData
     */
    public function persistGeneratedForm(User $user, array $parsedData): Form
    {
        $form = DB::transaction(function () use ($user, $parsedData): Form {
            $form = $this->formService->create($user, [
                'title' => $parsedData['title'],
                'description' => $parsedData['description'] ?? null,
            ]);

            foreach ($parsedData['fields'] as $fieldData) {
                $this->formBuilderService->addField($form, $fieldData);
            }

            return $form;
        });

        return $form->load('fields');
    }

    /**
     * Preview AI edits against an existing form schema without saving.
     *
     * @return array{title: string, description: string|null, settings?: array<string, mixed>, fields: array<int, array<string, mixed>>}
     */
    public function previewEdit(User $user, Form $form, string $prompt, ?AiGenerationLog $log = null): array
    {
        $current = $this->schemaService->export($form);
        $builtPrompt = $this->promptBuilder->buildEdit(json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $prompt);
        $providerName = (string) config('ai.default');
        $started = microtime(true);

        $log ??= AiGenerationLog::query()->create([
            'user_id' => $user->id,
            'form_id' => $form->id,
            'prompt' => $prompt,
            'provider' => $providerName,
            'model' => config("ai.providers.{$providerName}.model"),
            'status' => 'generating',
            'mode' => 'edit',
            'generated_at' => now(),
        ]);

        $log->update([
            'form_id' => $form->id,
            'status' => 'generating',
            'mode' => 'edit',
        ]);

        try {
            $response = $this->generateWithRetry($builtPrompt);
            $parsed = $this->parser->parse($response['content']);
            $normalized = $this->schemaService->validateSchema([
                'title' => $parsed['title'],
                'description' => $parsed['description'] ?? null,
                'settings' => $current['settings'] ?? [],
                'fields' => array_map(static function (array $field): array {
                    return [
                        'label' => $field['label'],
                        'name' => $field['name'] ?? null,
                        'type' => $field['type'],
                        'required' => $field['is_required'] ?? false,
                        'placeholder' => $field['placeholder'] ?? null,
                        'help_text' => $field['help_text'] ?? null,
                        'validation_rules' => $field['validation_rules'] ?? [],
                        'options' => $field['field_options'] ?? [],
                        'step' => max(1, (int) ($field['step'] ?? 1)),
                        'settings' => $field['settings'] ?? [],
                    ];
                }, $parsed['fields']),
            ]);

            $log->update([
                'response' => ['raw' => $response['content'], 'parsed' => $normalized],
                'tokens' => $response['tokens'],
                'model' => $response['model'] ?? $log->model,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'status' => 'completed',
                'error_message' => null,
            ]);

            return $normalized;
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_message' => $e instanceof AIException
                    ? $e->publicMessage()
                    : 'Unable to generate form. Please try again.',
            ]);

            throw $e;
        }
    }

    /**
     * @return array{content: string, tokens: int|null, model: string}
     */
    protected function generateWithRetry(string $prompt): array
    {
        $maxAttempts = (int) config('ai.retry.max_attempts', 3);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->provider->generate($prompt);
                $this->parser->parse($response['content']);

                return $response;
            } catch (InvalidAIResponseException $e) {
                $lastException = $e;
                Log::info('AI response invalid, retrying', ['attempt' => $attempt, 'max' => $maxAttempts]);
            }
        }

        throw InvalidAIResponseException::invalidJson('Unable to generate form. Please try again.');
    }
}
