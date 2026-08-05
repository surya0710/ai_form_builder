<?php

namespace App\Livewire\Public;

use App\Enums\FormStatus;
use App\Models\Form;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class FormRenderer extends Component
{
    public Form $form;

    public bool $isPreview = false;

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
    }

    public function render(): View
    {
        return view('livewire.public.form-renderer');
    }
}
