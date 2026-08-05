<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormStatus;
use App\Http\Requests\Api\SubmitFormRequest;
use App\Http\Resources\FormResource;
use App\Http\Resources\SubmissionResource;
use App\Models\Form;
use App\Services\Form\SubmissionService;
use Illuminate\Http\JsonResponse;

class PublicFormController extends Controller
{
    public function show(string $uuid): JsonResponse
    {
        $form = Form::query()
            ->with('fields')
            ->where('uuid', $uuid)
            ->where('status', FormStatus::Published)
            ->firstOrFail();

        return $this->success(new FormResource($form), 'Public form retrieved successfully.');
    }

    public function submit(SubmitFormRequest $request, string $uuid, SubmissionService $submissions): JsonResponse
    {
        $form = Form::query()
            ->with('fields')
            ->where('uuid', $uuid)
            ->where('status', FormStatus::Published)
            ->firstOrFail();

        $submission = $submissions->submit(
            $form,
            $request->except('_token') + $request->allFiles(),
            $request->user('sanctum'),
            $request->ip(),
            $request->userAgent()
        );

        return $this->success(new SubmissionResource($submission->load(['form', 'answers.field'])), 'Form submitted successfully.', 201);
    }
}
