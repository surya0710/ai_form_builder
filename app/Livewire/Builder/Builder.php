<?php

namespace App\Livewire\Builder;

use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\FormBuilderService;
use App\Services\Form\FormService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Builder extends Component
{
    public Form $form;

    public string $newLabel = '';

    public string $newType = 'text';

    public ?string $statusMessage = null;

    /** @var array<string, mixed> */
    public array $editor = [];

    public bool $showPublishModal = false;

    public string $embedWidth = '100%';

    public int $embedHeight = 800;

    public function mount(Form $form): void
    {
        Gate::authorize('update', $form);
        $this->form = $form;
        $this->statusMessage = session('status');

        if (session('show_publish_modal')) {
            $this->showPublishModal = true;
        }
    }

    public function addField(FormBuilderService $builder): void
    {
        $this->validate(['newLabel' => ['required', 'string', 'max:255'], 'newType' => ['required', Rule::in(config('forms.supported_field_types'))]]);
        $builder->addField($this->form, ['label' => $this->newLabel, 'type' => $this->newType]);
        $this->reset('newLabel');
        $this->statusMessage = 'Field added and saved.';
        $this->dispatch('field-updated');
    }

    public function publishForm(FormService $forms): void
    {
        Gate::authorize('update', $this->form);
        $this->form = $forms->publish($this->form);
        $this->statusMessage = 'Form published. It is now available on the public link.';
        $this->showPublishModal = true;
    }

    public function openShareModal(): void
    {
        Gate::authorize('update', $this->form);
        $this->showPublishModal = true;
    }

    public function closePublishModal(): void
    {
        $this->showPublishModal = false;
    }

    public function archiveForm(FormService $forms): void
    {
        Gate::authorize('update', $this->form);
        $this->form = $forms->archive($this->form);
        $this->statusMessage = 'Form archived.';
        $this->showPublishModal = false;
    }

    public function editField(int $fieldId): void
    {
        $field = $this->ownedField($fieldId);
        $this->editor = [
            ...$field->only(['id', 'label', 'name', 'placeholder', 'help_text', 'default_value', 'is_required']),
            'type' => $field->type instanceof \BackedEnum ? $field->type->value : (string) $field->type,
            'validation_rules_text' => implode(',', $field->validation_rules ?? []),
            'field_options_text' => implode("\n", $field->field_options ?? []),
        ];
    }

    public function saveField(FormBuilderService $builder): void
    {
        $this->validate(['editor.label' => ['required', 'string', 'max:255'], 'editor.name' => ['nullable', 'alpha_dash', 'max:255'], 'editor.type' => ['required', Rule::in(config('forms.supported_field_types'))]]);
        $builder->updateField($this->ownedField((int) $this->editor['id']), [...$this->editor, 'validation_rules' => array_filter(array_map('trim', explode(',', (string) ($this->editor['validation_rules_text'] ?? '')))), 'field_options' => array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($this->editor['field_options_text'] ?? '')) ?: []))]);
        $this->editor = [];
        $this->statusMessage = 'Field settings saved.';
        $this->dispatch('field-updated');
    }

    public function deleteField(int $fieldId, FormBuilderService $builder): void
    {
        $builder->deleteField($this->ownedField($fieldId));
        if (($this->editor['id'] ?? null) === $fieldId) {
            $this->editor = [];
        }
        $this->dispatch('field-updated');
    }

    public function move(int $fieldId, string $direction, FormBuilderService $builder): void
    {
        $ids = $this->form->fields()->orderBy('sort_order')->pluck('id')->all();
        $index = array_search($fieldId, $ids, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index !== false && isset($ids[$target])) {
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            $builder->reorderFields($this->form, $ids);
        }
        $this->dispatch('field-updated');
    }

    public function render(): View
    {
        return view('livewire.builder.builder', ['fields' => $this->form->fields()->orderBy('sort_order')->get(), 'fieldTypes' => config('forms.supported_field_types')]);
    }

    private function ownedField(int $fieldId): FormField
    {
        return $this->form->fields()->findOrFail($fieldId);
    }
}
