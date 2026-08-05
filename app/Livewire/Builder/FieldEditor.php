<?php

namespace App\Livewire\Builder;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class FieldEditor extends Component
{
    public function render(): View
    {
        return view('livewire.builder.field-editor');
    }
}
