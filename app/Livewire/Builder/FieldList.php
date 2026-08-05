<?php

namespace App\Livewire\Builder;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class FieldList extends Component
{
    public function render(): View
    {
        return view('livewire.builder.field-list');
    }
}
