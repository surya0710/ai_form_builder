<?php

namespace App\Livewire\Forms;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public function render(): View
    {
        return view('livewire.forms.create');
    }
}
