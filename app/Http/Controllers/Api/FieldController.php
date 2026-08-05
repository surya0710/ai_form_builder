<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ReorderFieldsRequest;
use App\Http\Requests\Api\StoreFieldRequest;
use App\Http\Requests\Api\UpdateFieldRequest;
use App\Http\Resources\FormFieldResource;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\FormBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FieldController extends Controller
{
    public function store(StoreFieldRequest $request, Form $form, FormBuilderService $builder): JsonResponse
    {
        Gate::authorize('update', $form);
        $field = $builder->addField($form, $request->validated());

        return $this->success(new FormFieldResource($field), 'Field created successfully.', 201);
    }

    public function update(UpdateFieldRequest $request, FormField $field, FormBuilderService $builder): JsonResponse
    {
        Gate::authorize('update', $field->form);

        $field = $builder->updateField($field, $request->validated());

        return $this->success(new FormFieldResource($field), 'Field updated successfully.');
    }

    public function destroy(FormField $field, FormBuilderService $builder): JsonResponse
    {
        Gate::authorize('update', $field->form);
        $builder->deleteField($field);

        return $this->success([], 'Field deleted successfully.');
    }

    public function duplicate(FormField $field, FormBuilderService $builder): JsonResponse
    {
        Gate::authorize('update', $field->form);
        $newField = $builder->duplicateField($field);

        return $this->success(new FormFieldResource($newField), 'Field duplicated successfully.', 201);
    }

    public function reorder(ReorderFieldsRequest $request, Form $form, FormBuilderService $builder): JsonResponse
    {
        Gate::authorize('update', $form);
        $builder->reorderFields($form, $request->validated('field_ids'));

        $fields = FormFieldResource::collection($form->fields()->orderBy('sort_order')->get());

        return $this->success($fields, 'Fields reordered successfully.');
    }
}
