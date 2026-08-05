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

    /**
     * Add a field and place it at a canvas index (0-based).
     *
     * @param  array<string, mixed>  $field
     */
    public function addFieldAt(Form $form, array $field, int $index): FormField
    {
        $created = $this->addField($form, $field);
        $this->insertAt($form, $created, $index);

        return $created->refresh();
    }

    /**
     * Insert an existing field at a canvas index and shift siblings.
     */
    public function insertAt(Form $form, FormField $field, int $index): void
    {
        $ids = $form->fields()
            ->whereKeyNot($field->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $index = max(0, min($index, count($ids)));
        array_splice($ids, $index, 0, [$field->id]);
        $this->reorderFields($form, array_map('intval', $ids));
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
        return $this->addField($field->form, [
            ...$field->only([
                'label', 'type', 'placeholder', 'help_text', 'default_value',
                'validation_rules', 'field_options', 'is_required', 'settings', 'step',
            ]),
            'label' => $field->label.' Copy',
            'sort_order' => ((int) $field->form->fields()->max('sort_order')) + 1,
        ]);
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(Form $form, array $attributes, ?FormField $field = null): array
    {
        if (array_key_exists('name', $attributes)) {
            $name = Str::slug((string) $attributes['name'], '_') ?: 'field';
        } elseif ($field?->name) {
            $name = $field->name;
        } else {
            $name = Str::slug((string) ($attributes['label'] ?? 'field'), '_') ?: 'field';
        }
        $name = $this->uniqueName($form, $name, $field);
        $type = $attributes['type'] ?? $field?->type ?? FieldType::Text;

        $payload = [
            'label' => $attributes['label'] ?? $field?->label,
            'name' => $name,
            'type' => $type,
            'placeholder' => array_key_exists('placeholder', $attributes) ? $attributes['placeholder'] : $field?->placeholder,
            'help_text' => array_key_exists('help_text', $attributes) ? $attributes['help_text'] : $field?->help_text,
            'default_value' => array_key_exists('default_value', $attributes) ? $attributes['default_value'] : $field?->default_value,
            'validation_rules' => array_values(array_filter($attributes['validation_rules'] ?? $field?->validation_rules ?? [])),
            'field_options' => array_values(array_filter($attributes['field_options'] ?? $field?->field_options ?? [])),
            'is_required' => (bool) ($attributes['is_required'] ?? $field?->is_required ?? false),
            'step' => max(1, (int) ($attributes['step'] ?? $field?->step ?? 1)),
            'settings' => $attributes['settings'] ?? $field?->settings ?? [],
        ];

        if ($field === null) {
            $payload['sort_order'] = $attributes['sort_order'] ?? ((int) $form->fields()->max('sort_order') + 1);
        } elseif (array_key_exists('sort_order', $attributes)) {
            $payload['sort_order'] = (int) $attributes['sort_order'];
        }

        return $payload;
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
