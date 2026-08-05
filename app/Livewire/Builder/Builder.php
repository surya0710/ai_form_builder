<?php

namespace App\Livewire\Builder;

use App\Jobs\EditAiFormJob;
use App\Models\AiGenerationLog;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\FormBuilderService;
use App\Services\Form\FormSchemaService;
use App\Services\Form\FormService;
use App\Services\Form\ValidationRuleCompiler;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Builder extends Component
{
    public Form $form;

    public string $tab = 'canvas';

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public bool $showPublishModal = false;

    public string $embedWidth = '100%';

    public int $embedHeight = 800;

    public string $schemaJson = '';

    public string $aiPrompt = '';

    /** @var array{title?: string, description?: string|null, fields?: array}|null */
    public ?array $aiPreview = null;

    public bool $showAiPanel = false;

    public ?int $expandedFieldId = null;

    public ?int $aiJobLogId = null;

    public string $aiQueueStatus = '';

    public bool $aiGenerating = false;

    public function mount(Form $form): void
    {
        Gate::authorize('update', $form);
        $this->form = $form;
        $this->statusMessage = session('status');
        $this->syncSchemaFromForm();

        if (session('show_publish_modal')) {
            $this->showPublishModal = true;
        }
    }

    public function selectField(int $fieldId): void
    {
        $this->ownedField($fieldId);
        $this->expandedFieldId = $fieldId;
    }

    public function collapseField(): void
    {
        $this->expandedFieldId = null;
    }

    /**
     * Click-to-add (end of form) or drop-from-palette (optional index).
     */
    public function addFieldByType(string $type, ?int $index = null): void
    {
        $this->errorMessage = null;

        $palette = collect(config('forms.palette_field_types', []));
        $entry = $palette->firstWhere('type', $type);

        if ($entry === null) {
            $this->errorMessage = 'Unsupported field type.';

            return;
        }

        $payload = [
            'label' => $entry['default_label'] ?? $entry['label'] ?? ucfirst($type),
            'type' => $type,
        ];

        if (in_array($type, ['select', 'radio', 'checkbox'], true)) {
            $payload['field_options'] = ['Option 1', 'Option 2'];
        }

        $builder = app(FormBuilderService::class);

        try {
            if ($index === null) {
                $builder->addField($this->form, $payload);
            } else {
                $builder->addFieldAt($this->form, $payload, $index);
            }
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Unable to add field.';

            return;
        }

        $this->statusMessage = 'Field added.';
        $this->refreshForm();
    }

    public function updateInline(int $fieldId, string $property, mixed $value, FormBuilderService $builder): void
    {
        $allowed = ['label', 'name', 'placeholder', 'help_text', 'default_value', 'is_required', 'step'];
        if (! in_array($property, $allowed, true)) {
            return;
        }

        $field = $this->ownedField($fieldId);
        $payload = [$property => $property === 'is_required' ? (bool) $value : $value];

        if ($property === 'label' && trim((string) $value) === '') {
            $this->errorMessage = 'Label cannot be empty.';

            return;
        }

        if ($property === 'name' && trim((string) $value) === '') {
            $this->errorMessage = 'Key cannot be empty.';

            return;
        }

        try {
            $builder->updateField($field, $payload);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Unable to update field.';

            return;
        }

        $this->errorMessage = null;
        $this->refreshForm();
        $this->statusMessage = 'Field updated.';
    }

    public function updateOptions(int $fieldId, string $text, FormBuilderService $builder): void
    {
        $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        $builder->updateField($this->ownedField($fieldId), ['field_options' => $options]);
        $this->refreshForm();
        $this->statusMessage = 'Options saved.';
    }

    /**
     * @param  array<string, mixed>  $editor
     */
    public function updateValidationEditor(int $fieldId, array $editor, FormBuilderService $builder, ValidationRuleCompiler $compiler): void
    {
        $rules = $compiler->fromEditor($editor);
        $builder->updateField($this->ownedField($fieldId), ['validation_rules' => $rules]);
        $this->refreshForm();
        $this->statusMessage = 'Validation saved.';
    }

    public function duplicateField(int $fieldId, FormBuilderService $builder): void
    {
        $builder->duplicateField($this->ownedField($fieldId));
        $this->refreshForm();
        $this->statusMessage = 'Field duplicated.';
    }

    public function deleteField(int $fieldId, FormBuilderService $builder): void
    {
        $builder->deleteField($this->ownedField($fieldId));
        if ($this->expandedFieldId === $fieldId) {
            $this->expandedFieldId = null;
        }
        $this->refreshForm();
        $this->statusMessage = 'Field deleted.';
    }

    /** @param array<int, int|string> $orderedIds */
    public function reorder(array $orderedIds, FormBuilderService $builder): void
    {
        $ids = array_map('intval', $orderedIds);
        $builder->reorderFields($this->form, $ids);
        $this->refreshForm();
        $this->statusMessage = 'Field order saved.';
    }

    public function switchTab(string $tab, FormSchemaService $schemas): void
    {
        if ($tab === 'schema') {
            $this->syncSchemaFromForm($schemas);
        }
        $this->tab = in_array($tab, ['canvas', 'schema'], true) ? $tab : 'canvas';
    }

    public function applySchema(FormSchemaService $schemas): void
    {
        $this->errorMessage = null;

        try {
            $decoded = json_decode($this->schemaJson, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                throw ValidationException::withMessages(['schemaJson' => 'Schema must be a JSON object.']);
            }
            $this->form = $schemas->apply($this->form, $decoded);
            $this->expandedFieldId = null;
            $this->syncSchemaFromForm($schemas);
            $this->statusMessage = 'Schema applied to canvas.';
            $this->tab = 'canvas';
        } catch (\JsonException) {
            $this->errorMessage = 'Invalid JSON. Please fix the schema and try again.';
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first() ?? 'Invalid schema.';
        }
    }

    public function openAiPanel(): void
    {
        $this->showAiPanel = true;
        $this->aiPreview = null;
        $this->errorMessage = null;
        $this->aiGenerating = false;
        $this->aiJobLogId = null;
        $this->aiQueueStatus = '';
    }

    public function closeAiPanel(): void
    {
        $this->showAiPanel = false;
        $this->aiPreview = null;
        $this->aiPrompt = '';
        $this->aiGenerating = false;
        $this->aiJobLogId = null;
        $this->aiQueueStatus = '';
    }

    public function generateAiEdit(): void
    {
        $this->validate(['aiPrompt' => ['required', 'string', 'min:3', 'max:1000']]);
        $this->errorMessage = null;
        $this->aiPreview = null;
        $this->aiGenerating = true;

        $providerName = (string) config('ai.default');
        $log = AiGenerationLog::query()->create([
            'user_id' => auth()->id(),
            'form_id' => $this->form->id,
            'prompt' => $this->aiPrompt,
            'provider' => $providerName,
            'model' => config("ai.providers.{$providerName}.model"),
            'status' => 'queued',
            'mode' => 'edit',
            'generated_at' => now(),
        ]);

        $this->aiJobLogId = $log->id;
        $this->aiQueueStatus = 'queued';

        EditAiFormJob::dispatch($log->id, (int) auth()->id(), $this->form->id, $this->aiPrompt);
        $this->pollAiEditJob();
    }

    public function pollAiEditJob(): void
    {
        if (! $this->aiJobLogId) {
            return;
        }

        $log = AiGenerationLog::query()->find($this->aiJobLogId);
        if (! $log) {
            return;
        }

        $this->aiQueueStatus = $log->status;

        if ($log->status === 'completed') {
            $this->aiPreview = $log->response['parsed'] ?? null;
            $this->aiGenerating = false;
            $this->aiJobLogId = null;
        }

        if ($log->status === 'failed') {
            $this->errorMessage = $log->error_message ?: 'Unable to generate form. Please try again.';
            $this->aiGenerating = false;
            $this->aiJobLogId = null;
        }
    }

    public function applyAiEdit(FormSchemaService $schemas): void
    {
        if ($this->aiPreview === null) {
            $this->errorMessage = 'Generate a preview before applying changes.';

            return;
        }

        $this->form = $schemas->apply($this->form, $this->aiPreview);
        $this->expandedFieldId = null;
        $this->syncSchemaFromForm($schemas);
        $this->closeAiPanel();
        $this->statusMessage = 'AI changes applied.';
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

    public function render(ValidationRuleCompiler $compiler): View
    {
        $fields = $this->form->fields()->orderBy('sort_order')->get();

        $validationEditors = $fields->mapWithKeys(
            fn (FormField $field) => [$field->id => $compiler->toEditor($field->validation_rules ?? [])]
        );

        return view('livewire.builder.builder', [
            'fields' => $fields,
            'paletteTypes' => config('forms.palette_field_types', []),
            'validationEditors' => $validationEditors,
            'expandedFieldId' => $this->expandedFieldId,
        ]);
    }

    private function refreshForm(?FormSchemaService $schemas = null): void
    {
        $this->form = $this->form->fresh(['fields']);
        if ($this->expandedFieldId !== null && ! $this->form->fields->contains('id', $this->expandedFieldId)) {
            $this->expandedFieldId = null;
        }
        $this->syncSchemaFromForm($schemas);
    }

    private function syncSchemaFromForm(?FormSchemaService $schemas = null): void
    {
        $schemas ??= app(FormSchemaService::class);
        $this->schemaJson = json_encode($schemas->export($this->form->fresh(['fields'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function ownedField(int $fieldId): FormField
    {
        return $this->form->fields()->findOrFail($fieldId);
    }
}
