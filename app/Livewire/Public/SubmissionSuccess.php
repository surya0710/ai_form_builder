<?php

namespace App\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class SubmissionSuccess extends Component
{
    public function render(): View
    {
        return view('livewire.public.submission-success');
    }
}
