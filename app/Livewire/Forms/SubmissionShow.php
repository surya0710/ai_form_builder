<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SubmissionShow extends Component
{
    public Form $form;

    public FormSubmission $submission;

    public function mount(Form $form, FormSubmission $submission): void
    {
        Gate::authorize('view', $form);
        abort_unless((int) $submission->form_id === (int) $form->id, 404);
        $this->form = $form;
        $this->submission = $submission->load(['answers.field']);
    }

    public function render(): View
    {
        return view('livewire.forms.submission-show');
    }
}
