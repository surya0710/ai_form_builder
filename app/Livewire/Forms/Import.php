<?php

namespace App\Livewire\Forms;

use App\Exceptions\Import\ImportException;
use App\Models\Form;
use App\Services\Import\ImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $step = 'upload';

    public ?string $fileName = null;

    public ?string $fileType = null;

    /** @var array{title?: string, description?: string|null, fields?: array<int, array<string, mixed>>}|null */
    public ?array $preview = null;

    public ?string $errorMessage = null;

    public function updatedFile(): void
    {
        $this->resetValidation();
        $this->errorMessage = null;
        $this->preview = null;
        $this->step = 'upload';
    }

    public function previewImport(ImportService $imports): void
    {
        Gate::authorize('create', Form::class);

        $this->validate([
            'file' => [
                'required',
                'file',
                'max:'.(int) config('forms.import.max_file_size_kb', 5120),
                'mimes:'.implode(',', config('forms.import.allowed_extensions', ['docx', 'xlsx'])),
            ],
        ], [
            'file.required' => 'Please upload a Word (.docx) or Excel (.xlsx) file.',
            'file.mimes' => 'Unsupported file type. Please upload a .docx or .xlsx file.',
        ]);

        try {
            $this->preview = $imports->preview($this->file);
            $this->fileName = $this->file->getClientOriginalName();
            $this->fileType = $this->preview['file_type'] ?? strtolower($this->file->getClientOriginalExtension());
            unset($this->preview['file_type']);
            $this->step = 'preview';
            $this->errorMessage = null;
        } catch (ImportException $e) {
            $this->errorMessage = $e->publicMessage();
            $this->step = 'upload';
        }
    }

    public function confirmImport(ImportService $imports)
    {
        Gate::authorize('create', Form::class);

        if ($this->preview === null || $this->fileName === null || $this->fileType === null) {
            $this->errorMessage = 'Please upload and preview a file before importing.';
            $this->step = 'upload';

            return null;
        }

        try {
            $form = $imports->importNormalized(
                auth()->user(),
                $this->preview,
                $this->fileName,
                $this->fileType,
            );

            return redirect()->route('forms.builder', $form)
                ->with('status', 'Form imported successfully. You can edit fields in the builder.');
        } catch (ImportException $e) {
            $this->errorMessage = $e->publicMessage();
            $this->step = 'preview';

            return null;
        }
    }

    public function resetImport(): void
    {
        $this->reset(['file', 'preview', 'fileName', 'fileType', 'errorMessage']);
        $this->step = 'upload';
    }

    public function render(): View
    {
        return view('livewire.forms.import');
    }
}
