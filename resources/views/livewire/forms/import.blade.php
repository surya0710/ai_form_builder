<div class="max-w-3xl mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Import Form</h1>
            <p class="text-sm text-gray-500 mt-1">Upload a Word (.docx) or Excel (.xlsx) file, preview fields, then import.</p>
        </div>
        <a href="{{ route('forms.index') }}" class="text-indigo-600 text-sm">Back to forms</a>
    </div>

    @if ($errorMessage)
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    @if ($step === 'upload')
        <div class="bg-white rounded shadow p-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700">Document</span>
                <input type="file" wire:model="file" accept=".docx,.xlsx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="mt-2 block w-full text-sm text-gray-600">
            </label>
            @error('file') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div wire:loading wire:target="file" class="text-sm text-gray-500">Uploading…</div>

            <div class="flex gap-3">
                <button type="button" wire:click="previewImport" wire:loading.attr="disabled" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="previewImport">Preview</span>
                    <span wire:loading wire:target="previewImport">Parsing…</span>
                </button>
            </div>

            <p class="text-xs text-gray-500">Excel files should include Label, Type, Required, and Options columns. Word files can list field labels with dash options under select fields.</p>
        </div>
    @endif

    @if ($step === 'preview' && $preview)
        <div class="bg-white rounded shadow p-6 space-y-5">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">Preview</p>
                <h2 class="text-xl font-semibold mt-1">{{ $preview['title'] }}</h2>
                @if (!empty($preview['description']))
                    <p class="text-gray-600 mt-1">{{ $preview['description'] }}</p>
                @endif
                <p class="text-sm text-gray-500 mt-2">{{ $fileName }} · {{ strtoupper($fileType) }} · {{ count($preview['fields']) }} field(s)</p>
            </div>

            <div class="divide-y border rounded">
                @foreach ($preview['fields'] as $field)
                    <div class="p-3 flex justify-between gap-4">
                        <div>
                            <div class="font-medium">{{ $field['label'] }}</div>
                            <div class="text-sm text-gray-500">
                                {{ $field['type'] }}
                                @if ($field['is_required']) · required @endif
                                @if (!empty($field['field_options']))
                                    · {{ implode(', ', $field['field_options']) }}
                                @endif
                            </div>
                        </div>
                        <div class="text-xs text-gray-400">{{ $field['name'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="flex gap-3">
                <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="confirmImport">Import</span>
                    <span wire:loading wire:target="confirmImport">Importing…</span>
                </button>
                <button type="button" wire:click="resetImport" class="px-4 py-2 border-2 border-gray-300 text-gray-700 font-semibold rounded-md hover:bg-gray-50">Choose another file</button>
            </div>
        </div>
    @endif
</div>
