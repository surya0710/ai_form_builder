<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SubmissionCollection;
use App\Http\Resources\SubmissionResource;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubmissionController extends Controller
{
    public function index(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('view', $form);

        $perPage = min(
            $request->query('per_page', config('forms.pagination.default')),
            config('forms.pagination.max')
        );

        $submissions = $form->submissions()
            ->with(['form', 'answers.field'])
            ->latest('submitted_at')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginated(new SubmissionCollection($submissions), 'Submissions retrieved successfully.');
    }

    public function show(FormSubmission $submission): JsonResponse
    {
        Gate::authorize('view', $submission->form);

        return $this->success(new SubmissionResource($submission->load(['form', 'answers.field'])), 'Submission retrieved successfully.');
    }

    public function destroy(FormSubmission $submission): JsonResponse
    {
        Gate::authorize('delete', $submission->form);
        $submission->delete();

        return $this->success([], 'Submission deleted successfully.');
    }
}
