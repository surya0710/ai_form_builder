<div class="max-w-6xl mx-auto p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Builder: {{ $form->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                Status:
                <span class="font-medium capitalize {{ $form->status === \App\Enums\FormStatus::Published ? 'text-green-700' : 'text-gray-700' }}">{{ $form->status->value }}</span>
                ·
                <a class="text-indigo-600" href="{{ route('forms.show', $form) }}" wire:navigate>Back to form</a>
            </p>
            <p class="text-sm text-gray-500 mt-1">Fields save automatically when you add or update them.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('public.forms.show', $form->uuid) }}" target="_blank" class="px-3 py-2 text-sm border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                Preview
            </a>

            @if ($form->status !== \App\Enums\FormStatus::Published)
                <button type="button" wire:click="publishForm" class="px-4 py-2 text-sm bg-green-600 text-white font-semibold border-2 border-green-800 rounded-md shadow-sm hover:bg-green-700">
                    Publish
                </button>
            @else
                <button type="button" wire:click="openShareModal" class="px-4 py-2 text-sm bg-green-600 text-white font-semibold border-2 border-green-800 rounded-md shadow-sm hover:bg-green-700">
                    Share / Embed
                </button>
                <button type="button" wire:click="archiveForm" class="px-4 py-2 text-sm border border-amber-300 text-amber-800 rounded hover:bg-amber-50">
                    Archive form
                </button>
            @endif

            <a href="{{ route('forms.show', $form) }}" wire:navigate class="px-4 py-2 text-sm bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">
                Done
            </a>
        </div>
    </div>

    @if ($statusMessage)
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-6">
        <section class="bg-white shadow rounded p-5">
            <h2 class="font-semibold mb-3">+ Add Field</h2>
            <div class="flex gap-2">
                <input wire:model="newLabel" placeholder="Field label" class="flex-1 rounded border-gray-300">
                <select wire:model="newType" class="rounded border-gray-300">
                    @foreach ($fieldTypes as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="addField" class="px-3 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">Add</button>
            </div>
            @error('newLabel')
                <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
            @enderror

            <h2 class="font-semibold mt-6 mb-3">Field List</h2>
            <div class="space-y-2">
                @forelse ($fields as $field)
                    <div class="border rounded p-3 flex justify-between items-center">
                        <button type="button" wire:click="editField({{ $field->id }})" class="text-left">
                            <span class="font-medium">{{ $field->label }}</span>
                            <span class="text-sm text-gray-500"> · {{ $field->type->value }} · {{ $field->name }}</span>
                        </button>
                        <div class="space-x-2 text-sm">
                            <button type="button" wire:click="move({{ $field->id }}, 'up')">↑</button>
                            <button type="button" wire:click="move({{ $field->id }}, 'down')">↓</button>
                            <button type="button" wire:click="deleteField({{ $field->id }})" class="text-red-700">Delete</button>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500">No fields yet.</p>
                @endforelse
            </div>
        </section>

        <section class="bg-white shadow rounded p-5">
            <h2 class="font-semibold mb-3">Field Settings</h2>
            @if ($editor)
                <div class="space-y-3">
                    <label class="block text-sm">Label<input wire:model="editor.label" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="block text-sm">Name<input wire:model="editor.name" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="block text-sm">Type
                        <select wire:model="editor.type" class="mt-1 w-full rounded border-gray-300">
                            @foreach ($fieldTypes as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">Placeholder<input wire:model="editor.placeholder" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="block text-sm">Help text<input wire:model="editor.help_text" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="block text-sm">Default value<input wire:model="editor.default_value" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="block text-sm"><input type="checkbox" wire:model="editor.is_required"> Required</label>
                    <label class="block text-sm">Validation rules <span class="text-gray-500">(comma separated)</span><input wire:model="editor.validation_rules_text" placeholder="required,email,max:255" class="mt-1 w-full rounded border-gray-300"></label>
                    <label class="block text-sm">Options <span class="text-gray-500">(one per line)</span><textarea wire:model="editor.field_options_text" class="mt-1 w-full rounded border-gray-300"></textarea></label>
                    <button type="button" wire:click="saveField" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">Save field</button>
                </div>
            @else
                <p class="text-gray-500">Select a field to configure it.</p>
            @endif
        </section>
    </div>

    <x-publish-modal
        :form="$form"
        :show="$showPublishModal"
        :embed-width="$embedWidth"
        :embed-height="$embedHeight"
    />
</div>
