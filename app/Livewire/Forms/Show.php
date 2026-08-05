<?php

namespace App\Livewire\Forms;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Services\Form\FormService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Form $form;

    public bool $showPublishModal = false;

    public string $embedWidth = '100%';

    public int $embedHeight = 800;

    public ?string $statusMessage = null;

    public function mount(Form $form): void
    {
        Gate::authorize('view', $form);
        $this->form = $form->load(['fields' => fn ($query) => $query->orderBy('sort_order')]);
        $this->statusMessage = session('status');

        if (session('show_publish_modal') || ($form->status === FormStatus::Published && request()->boolean('share'))) {
            $this->showPublishModal = true;
        }
    }

    public function publishForm(FormService $forms): void
    {
        Gate::authorize('update', $this->form);
        $this->form = $forms->publish($this->form)->load(['fields' => fn ($query) => $query->orderBy('sort_order')]);
        $this->statusMessage = 'Form published.';
        $this->showPublishModal = true;
    }

    public function openShareModal(): void
    {
        Gate::authorize('view', $this->form);
        $this->showPublishModal = true;
    }

    public function closePublishModal(): void
    {
        $this->showPublishModal = false;
    }

    public function archiveForm(FormService $forms): void
    {
        Gate::authorize('update', $this->form);
        $this->form = $forms->archive($this->form)->load(['fields' => fn ($query) => $query->orderBy('sort_order')]);
        $this->statusMessage = 'Form archived.';
        $this->showPublishModal = false;
    }

    public function render(): View
    {
        return view('livewire.forms.show');
    }
}
