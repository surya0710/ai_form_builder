<?php

namespace App\Services\Form;

use App\Enums\FieldType;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormBuilderService
{
    /** @param array<string, mixed> $field */
    public function addField(Form $form, array $field): FormField
    {
        if ($form->fields()->count() >= config('forms.maximum_fields')) {
            throw ValidationException::withMessages(['fields' => 'This form has reached its maximum number of fields.']);
        }

        return $form->fields()->create($this->attributes($form, $field));
    }

    /** @param array<string, mixed> $attributes */
    public function updateField(FormField $field, array $attributes): FormField
    {
        $field->update($this->attributes($field->form, $attributes, $field));

        return $field->refresh();
    }

    public function deleteField(FormField $field): void
    {
        $field->delete();
    }

    /** @param array<int, int> $fieldIds */
    public function reorderFields(Form $form, array $fieldIds): void
    {
        if ($form->fields()->whereIn('id', $fieldIds)->count() !== count(array_unique($fieldIds))) {
            throw ValidationException::withMessages(['fields' => 'Every field must belong to this form.']);
        }
        DB::transaction(function () use ($form, $fieldIds): void {
            foreach ($fieldIds as $sortOrder => $fieldId) {
                $form->fields()->whereKey($fieldId)->update(['sort_order' => $sortOrder]);
            }
        });
    }

    public function duplicateField(FormField $field): FormField
    {
        return $this->addField($field->form, [...$field->only(['label', 'type', 'placeholder', 'help_text', 'default_value', 'validation_rules', 'field_options', 'is_required', 'settings']), 'label' => $field->label.' Copy']);
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(Form $form, array $attributes, ?FormField $field = null): array
    {
        $name = Str::slug((string) ($attributes['name'] ?? $attributes['label'] ?? $field?->label), '_') ?: 'field';
        $name = $this->uniqueName($form, $name, $field);

        return ['label' => $attributes['label'] ?? $field?->label, 'name' => $name, 'type' => $attributes['type'] ?? $field?->type ?? FieldType::Text,
            'placeholder' => $attributes['placeholder'] ?? null, 'help_text' => $attributes['help_text'] ?? null, 'default_value' => $attributes['default_value'] ?? null,
            'validation_rules' => array_values(array_filter($attributes['validation_rules'] ?? $field?->validation_rules ?? [])), 'field_options' => array_values(array_filter($attributes['field_options'] ?? $field?->field_options ?? [])),
            'is_required' => (bool) ($attributes['is_required'] ?? false), 'sort_order' => $attributes['sort_order'] ?? ($field?->sort_order ?? ((int) $form->fields()->max('sort_order') + 1)), 'settings' => $attributes['settings'] ?? $field?->settings ?? []];
    }

    private function uniqueName(Form $form, string $base, ?FormField $field): string
    {
        $name = $base;
        $suffix = 2;
        while ($form->fields()->where('name', $name)->when($field, fn ($query) => $query->whereKeyNot($field->id))->exists()) {
            $name = $base.'_'.$suffix++;
        }

        return $name;
    }
}
