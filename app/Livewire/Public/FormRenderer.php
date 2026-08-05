<?php

namespace App\Livewire\Public;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Services\Form\SubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.public')]
class FormRenderer extends Component
{
    use WithFileUploads;

    public Form $form;

    public bool $isPreview = false;

    public int $currentStep = 1;

    public int $totalSteps = 1;

    /** @var array<string, mixed> */
    public array $answers = [];

    public bool $submitted = false;

    public function mount(string $uuid): void
    {
        $form = Form::query()
            ->with(['fields' => fn ($query) => $query->orderBy('sort_order')])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $isOwner = Auth::check() && (int) Auth::id() === (int) $form->user_id;
        $isPublished = $form->status === FormStatus::Published;

        if (! $isPublished && ! $isOwner) {
            abort(404);
        }

        $this->form = $form;
        $this->isPreview = ! $isPublished;
        $this->totalSteps = max(1, (int) $form->fields->max('step'));
        $this->submitted = (bool) session('submission_success');

        foreach ($form->fields as $field) {
            $type = $field->type instanceof FieldType ? $field->type : FieldType::tryFrom((string) $field->type);
            if ($type === FieldType::Section) {
                continue;
            }
            $this->answers[$field->name] = old($field->name, $field->default_value);
        }
    }

    public function nextStep(SubmissionService $submissions): void
    {
        if ($this->isPreview) {
            if ($this->currentStep < $this->totalSteps) {
                $this->currentStep++;
            }

            return;
        }

        try {
            $validated = $submissions->validate($this->form, $this->answers, $this->currentStep);
            foreach ($validated as $key => $value) {
                $this->answers[$key] = $value;
            }
        } catch (ValidationException $e) {
            $mapped = [];
            foreach ($e->errors() as $key => $messages) {
                $mapped['answers.'.$key] = $messages;
            }
            throw ValidationException::withMessages($mapped);
        }

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function submit(SubmissionService $submissions)
    {
        if ($this->isPreview) {
            return null;
        }

        try {
            $submissions->validate($this->form, $this->answers, $this->currentStep);
        } catch (ValidationException $e) {
            $mapped = [];
            foreach ($e->errors() as $key => $messages) {
                $mapped['answers.'.$key] = $messages;
            }
            throw ValidationException::withMessages($mapped);
        }

        $submissions->submit(
            $this->form,
            $this->answers,
            Auth::user(),
            request()->ip(),
            request()->userAgent(),
        );

        return redirect()->route('public.forms.show', $this->form->uuid)
            ->with('submission_success', true);
    }

    public function render(): View
    {
        $stepFields = $this->form->fields
            ->where('step', $this->currentStep)
            ->values();

        return view('livewire.public.form-renderer', [
            'stepFields' => $stepFields,
        ]);
    }
}
