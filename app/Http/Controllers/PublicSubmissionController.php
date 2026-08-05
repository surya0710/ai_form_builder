<?php

namespace App\Http\Controllers;

use App\Enums\FormStatus;
use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Form;
use App\Services\Form\SubmissionService;
use Illuminate\Http\RedirectResponse;

class PublicSubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, string $uuid, SubmissionService $submissions): RedirectResponse
    {
        $form = Form::query()->with('fields')->where('uuid', $uuid)->where('status', FormStatus::Published)->firstOrFail();
        $submissions->submit($form, $request->except('_token') + $request->allFiles(), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('public.forms.show', $form->uuid)->with('submission_success', true);
    }
}
