<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Form $form;

    public function mount(Form $form): void
    {
        Gate::authorize('update', $form);
        $this->form = $form;
    }

    public function render(): View
    {
        return view('livewire.forms.edit');
    }
}
