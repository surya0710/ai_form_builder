<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\GenerateAIFormRequest;
use App\Http\Requests\Api\ImportFormRequest;
use App\Http\Requests\Api\StoreFormRequest;
use App\Http\Requests\Api\UpdateFormRequest;
use App\Http\Resources\FormCollection;
use App\Http\Resources\FormResource;
use App\Models\Form;
use App\Services\AI\AIService;
use App\Services\Form\FormService;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormController extends Controller
{
    public function generate(GenerateAIFormRequest $request, AIService $aiService): JsonResponse
    {
        Gate::authorize('create', Form::class);
        $form = $aiService->generateForm($request->user(), $request->validated('prompt'));

        return $this->success(new FormResource($form), 'Form generated successfully.', 201);
    }

    public function import(ImportFormRequest $request, ImportService $imports): JsonResponse
    {
        Gate::authorize('create', Form::class);
        $form = $imports->import($request->user(), $request->file('file'));

        return $this->success(new FormResource($form), 'Form imported successfully.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            $request->query('per_page', config('forms.pagination.default')),
            config('forms.pagination.max')
        );

        $forms = $request->user()->forms()
            ->withCount(['fields', 'submissions'])
            ->status($request->query('status'))
            ->search($request->query('search'))
            ->sort($request->query('sort', '-created_at'))
            ->when($request->query('created_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->query('created_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginated(new FormCollection($forms), 'Forms retrieved successfully.');
    }

    public function store(StoreFormRequest $request, FormService $forms): JsonResponse
    {
        Gate::authorize('create', Form::class);
        $form = $forms->create($request->user(), $request->validated());

        return $this->success(new FormResource($form), 'Form created successfully.', 201);
    }

    public function show(Form $form): JsonResponse
    {
        Gate::authorize('view', $form);

        return $this->success(new FormResource($form->load('fields')), 'Form retrieved successfully.');
    }

    public function update(UpdateFormRequest $request, Form $form, FormService $forms): JsonResponse
    {
        Gate::authorize('update', $form);

        $form = $forms->update($form, $request->validated());

        return $this->success(new FormResource($form), 'Form updated successfully.');
    }

    public function destroy(Form $form, FormService $forms): JsonResponse
    {
        Gate::authorize('delete', $form);
        $forms->delete($form);

        return $this->success([], 'Form deleted successfully.');
    }

    public function publish(Form $form, FormService $forms): JsonResponse
    {
        Gate::authorize('update', $form);
        $form = $forms->publish($form);

        return $this->success(new FormResource($form), 'Form published successfully.');
    }

    public function archive(Form $form, FormService $forms): JsonResponse
    {
        Gate::authorize('update', $form);
        $form = $forms->archive($form);

        return $this->success(new FormResource($form), 'Form archived successfully.');
    }
}
