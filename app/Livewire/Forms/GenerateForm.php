<?php

namespace App\Livewire\Forms;

use App\Exceptions\AI\AIException;
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

    public function generate(AIService $ai): void
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

        try {
            $this->preview = $ai->generatePreview(auth()->user(), $this->prompt);
            $this->step = 'preview';
        } catch (AIException $e) {
            $this->errorMessage = $e->publicMessage();
            $this->step = 'prompt';
        } catch (Throwable) {
            $this->errorMessage = 'Something went wrong while generating your form. Please try again.';
            $this->step = 'prompt';
        } finally {
            $this->generating = false;
        }
    }

    public function regenerate(AIService $ai): void
    {
        $this->generate($ai);
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
        $this->reset(['preview', 'errorMessage', 'generating']);
        $this->step = 'prompt';
    }

    public function render(): View
    {
        return view('livewire.forms.generate');
    }
}
