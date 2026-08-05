<?php

namespace App\Livewire\Forms;

use App\Jobs\GenerateAiFormJob;
use App\Models\AiGenerationLog;
use App\Models\Form;
use App\Services\AI\AIService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class GenerateForm extends Component
{
    public string $prompt = '';

    public string $step = 'prompt';

    /** @var array{title?: string, description?: string|null, fields?: array<int, array<string, mixed>>}|null */
    public ?array $preview = null;

    public ?string $errorMessage = null;

    public bool $generating = false;

    public ?int $jobLogId = null;

    public string $queueStatus = '';

    /** @var list<string> */
    public array $examples = [
        'Employee Feedback Form',
        'Customer Survey',
        'Job Application',
        'Hospital Registration',
        'Event Registration',
        'Loan Application',
    ];

    public function useExample(string $example): void
    {
        $this->prompt = 'Create a '.$example.' with relevant fields.';
        $this->resetValidation();
        $this->errorMessage = null;
    }

    public function generate(): void
    {
        Gate::authorize('create', Form::class);

        $this->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'prompt.required' => 'Please describe the form you want to generate.',
            'prompt.min' => 'Your description must be at least 3 characters.',
            'prompt.max' => 'Your description may not be longer than 1000 characters.',
        ]);

        $this->generating = true;
        $this->errorMessage = null;
        $this->preview = null;

        $providerName = (string) config('ai.default');
        $log = AiGenerationLog::query()->create([
            'user_id' => auth()->id(),
            'prompt' => $this->prompt,
            'provider' => $providerName,
            'model' => config("ai.providers.{$providerName}.model"),
            'status' => 'queued',
            'mode' => 'generate',
            'generated_at' => now(),
        ]);

        $this->jobLogId = $log->id;
        $this->queueStatus = 'queued';

        GenerateAiFormJob::dispatch($log->id, (int) auth()->id(), $this->prompt, false);
        $this->pollJob();
    }

    public function pollJob(): void
    {
        if (! $this->jobLogId) {
            return;
        }

        $log = AiGenerationLog::query()->find($this->jobLogId);
        if (! $log) {
            return;
        }

        $this->queueStatus = $log->status;

        if ($log->status === 'completed') {
            $this->preview = $log->response['parsed'] ?? null;
            $this->step = 'preview';
            $this->generating = false;
            $this->jobLogId = null;
        }

        if ($log->status === 'failed') {
            $this->errorMessage = $log->error_message ?: 'Unable to generate form. Please try again.';
            $this->step = 'prompt';
            $this->generating = false;
            $this->jobLogId = null;
        }
    }

    public function regenerate(): void
    {
        $this->generate();
    }

    public function saveForm(AIService $ai)
    {
        Gate::authorize('create', Form::class);

        if ($this->preview === null || empty($this->preview['fields'])) {
            $this->errorMessage = 'Please generate a form preview before saving.';
            $this->step = 'prompt';

            return null;
        }

        try {
            $form = $ai->persistGeneratedForm(auth()->user(), $this->preview);

            return redirect()->route('forms.builder', $form)
                ->with('status', 'AI form saved. You can edit fields before publishing.');
        } catch (Throwable) {
            $this->errorMessage = 'Could not save the generated form. Please try again.';
            $this->step = 'preview';

            return null;
        }
    }

    public function resetPrompt(): void
    {
        $this->reset(['preview', 'errorMessage', 'generating', 'jobLogId', 'queueStatus']);
        $this->step = 'prompt';
    }

    public function render(): View
    {
        return view('livewire.forms.generate');
    }
}
