<?php

namespace App\Livewire\Forms;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.forms.index', ['forms' => auth()->user()->forms()->withCount(['fields', 'submissions'])->latest()->get()]);
    }
}
