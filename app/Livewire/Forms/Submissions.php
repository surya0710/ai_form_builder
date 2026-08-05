<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Form\SubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class Submissions extends Component
{
    use WithPagination;

    public Form $form;

    #[Url]
    public string $search = '';

    public function mount(Form $form): void
    {
        Gate::authorize('view', $form);
        $this->form = $form;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function deleteSubmission(int $submissionId, SubmissionService $submissions): void
    {
        Gate::authorize('update', $this->form);
        $submission = $this->form->submissions()->findOrFail($submissionId);
        $submissions->delete($submission);
        session()->flash('status', 'Submission deleted.');
    }

    public function exportCsv(SubmissionService $submissions): StreamedResponse
    {
        Gate::authorize('view', $this->form);

        return $submissions->exportCsv($this->form);
    }

    public function render(): View
    {
        $query = $this->form->submissions()->withCount('answers')->latest('submitted_at');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('uuid', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhereHas('answers', fn ($aq) => $aq->where('answer', 'like', $term));
            });
        }

        return view('livewire.forms.submissions', [
            'submissions' => $query->paginate(config('forms.pagination.default', 15)),
        ]);
    }
}
