<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIException;
use App\Models\AiGenerationLog;
use App\Models\Form;
use App\Models\User;
use App\Services\AI\Providers\AIProviderInterface;
use App\Services\Form\FormBuilderService;
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
    ) {}

    /**
     * Orchestrate prompt building, provider call, parsing, persistence, and logging.
     *
     * @throws AIException|Throwable
     */
    public function generateForm(User $user, string $prompt): Form
    {
        $parsedData = $this->generatePreview($user, $prompt);

        return $this->persistGeneratedForm($user, $parsedData);
    }

    /**
     * Call the AI provider and return a normalized form preview without persisting.
     *
     * @return array{title: string, description: string|null, fields: array<int, array<string, mixed>>}
     *
     * @throws AIException|Throwable
     */
    public function generatePreview(User $user, string $prompt): array
    {
        $builtPrompt = $this->promptBuilder->build($prompt);
        $providerName = (string) config('ai.default');

        $log = AiGenerationLog::query()->create([
            'user_id' => $user->id,
            'prompt' => $prompt,
            'provider' => $providerName,
            'model' => config("ai.providers.{$providerName}.model"),
            'status' => 'failed',
            'generated_at' => now(),
        ]);

        try {
            $response = $this->provider->generate($builtPrompt);

            $log->update([
                'response' => ['raw' => $response['content']],
                'tokens' => $response['tokens'],
                'model' => $response['model'] ?? $log->model,
            ]);

            $parsedData = $this->parser->parse($response['content']);

            $log->update(['status' => 'success', 'error_message' => null]);

            return $parsedData;
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
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
     * Persist a previously generated (previewed) form schema.
     *
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
}
