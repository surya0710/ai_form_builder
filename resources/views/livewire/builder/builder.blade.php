<div class="max-w-6xl mx-auto p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Builder: {{ $form->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                Status:
                <span class="font-medium capitalize {{ $form->status === \App\Enums\FormStatus::Published ? 'text-green-700' : 'text-gray-700' }}">{{ $form->status->value }}</span>
                ·
                <a class="text-indigo-600" href="{{ route('forms.show', $form) }}" wire:navigate>Back to form</a>
                ·
                <a class="text-indigo-600" href="{{ route('forms.submissions', $form) }}" wire:navigate>Submissions</a>
            </p>
            <p class="text-sm text-gray-500 mt-1">Drag from the palette or click to add. Click a field to edit. Esc collapses.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="openAiPanel" class="px-3 py-2 text-sm border-2 border-violet-600 text-violet-700 font-semibold rounded-md hover:bg-violet-50">
                Edit with AI
            </button>
            <a href="{{ route('public.forms.show', $form->uuid) }}" target="_blank" class="px-3 py-2 text-sm border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                Preview
            </a>
            @if ($form->status !== \App\Enums\FormStatus::Published)
                <button type="button" wire:click="publishForm" class="px-4 py-2 text-sm bg-green-600 text-white font-semibold border-2 border-green-800 rounded-md shadow-sm hover:bg-green-700">Publish</button>
            @else
                <button type="button" wire:click="openShareModal" class="px-4 py-2 text-sm bg-green-600 text-white font-semibold border-2 border-green-800 rounded-md shadow-sm hover:bg-green-700">Share / Embed</button>
                <button type="button" wire:click="archiveForm" class="px-4 py-2 text-sm border border-amber-300 text-amber-800 rounded hover:bg-amber-50">Archive form</button>
            @endif
            <a href="{{ route('forms.show', $form) }}" wire:navigate class="px-4 py-2 text-sm bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">Done</a>
        </div>
    </div>

    @if ($statusMessage)
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
    @endif
    @if ($errorMessage)
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="flex gap-2 mb-4 border-b">
        <button type="button" wire:click="switchTab('canvas')" class="px-4 py-2 text-sm font-semibold border-b-2 {{ $tab === 'canvas' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500' }}">Canvas</button>
        <button type="button" wire:click="switchTab('schema')" class="px-4 py-2 text-sm font-semibold border-b-2 {{ $tab === 'schema' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500' }}">Raw JSON</button>
    </div>

    @if ($tab === 'canvas')
        <div
            class="grid lg:grid-cols-3 gap-6"
            x-data="{
                paletteSortable: null,
                canvasSortable: null,
                initSortables() {
                    if (!window.Sortable) return;
                    const palette = this.$refs.palette;
                    const canvas = this.$refs.canvas;
                    if (!palette || !canvas) return;

                    if (this.paletteSortable) this.paletteSortable.destroy();
                    if (this.canvasSortable) this.canvasSortable.destroy();

                    this.paletteSortable = Sortable.create(palette, {
                        group: { name: 'builder-fields', pull: 'clone', put: false },
                        sort: false,
                        animation: 150,
                        draggable: '[data-field-type]',
                    });

                    this.canvasSortable = Sortable.create(canvas, {
                        group: 'builder-fields',
                        handle: '.drag-handle',
                        animation: 180,
                        ghostClass: 'opacity-40',
                        draggable: '[data-field-id]',
                        emptyInsertThreshold: 24,
                        onAdd: (evt) => {
                            const type = evt.item.dataset.fieldType;
                            const index = evt.newIndex;
                            evt.item.remove();
                            if (type) {
                                $wire.addFieldByType(type, index);
                            }
                        },
                        onEnd: (evt) => {
                            if (evt.from !== evt.to) return;
                            if (evt.oldIndex === evt.newIndex) return;
                            const ids = Array.from(canvas.querySelectorAll('[data-field-id]')).map(el => el.dataset.fieldId);
                            $wire.reorder(ids);
                        },
                    });
                },
                init() {
                    this.initSortables();
                    Livewire.hook('morph.updated', ({ el }) => {
                        if (el === this.$el || this.$el.contains(el)) {
                            queueMicrotask(() => this.initSortables());
                        }
                    });
                }
            }"
            @keydown.escape.window="
                const el = document.activeElement;
                if (el && typeof el.blur === 'function') el.blur();
                queueMicrotask(() => $wire.collapseField());
            "
        >
            <section class="lg:col-span-1 bg-white shadow rounded p-5 h-fit">
                <h2 class="font-semibold mb-1">Field palette</h2>
                <p class="text-xs text-gray-500 mb-3">Click to add at the end, or drag onto the canvas.</p>
                <div x-ref="palette" class="grid grid-cols-2 gap-2">
                    @foreach ($paletteTypes as $paletteType)
                        <button
                            type="button"
                            data-field-type="{{ $paletteType['type'] }}"
                            wire:click="addFieldByType('{{ $paletteType['type'] }}')"
                            class="cursor-grab active:cursor-grabbing text-left px-3 py-2 text-sm border border-gray-200 rounded-md bg-gray-50 hover:bg-indigo-50 hover:border-indigo-300 transition"
                            title="Click to add, or drag to canvas"
                        >
                            {{ $paletteType['label'] }}
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="lg:col-span-2 bg-white shadow rounded p-5">
                <h2 class="font-semibold mb-3">Form canvas ({{ $fields->count() }})</h2>
                <div class="relative">
                    <div
                        x-ref="canvas"
                        id="builder-canvas"
                        class="space-y-3 min-h-[12rem] rounded-lg border-2 border-dashed border-gray-200 p-3 bg-slate-50/50"
                    >
                        @foreach ($fields as $field)
                            @php
                                $type = $field->type instanceof \BackedEnum ? $field->type->value : (string) $field->type;
                                $typeLabel = collect($paletteTypes)->firstWhere('type', $type)['label'] ?? ucfirst($type);
                                $rulesEditor = $validationEditors[$field->id] ?? [];
                                $isExpanded = (int) $expandedFieldId === (int) $field->id;
                                $hasOptions = in_array($type, ['select', 'radio', 'checkbox'], true);
                            @endphp
                            <div
                                data-field-id="{{ $field->id }}"
                                wire:key="field-{{ $field->id }}"
                                class="border rounded-lg bg-white shadow-sm w-full transition {{ $isExpanded ? 'border-indigo-400 ring-1 ring-indigo-200' : 'border-gray-200 hover:border-gray-300' }}"
                            >
                                {{-- Collapsed summary (always visible; drag from here) --}}
                                <div
                                    class="flex items-start gap-3 p-3 sm:p-4 cursor-pointer"
                                    wire:click="selectField({{ $field->id }})"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter.prevent="$wire.selectField({{ $field->id }})"
                                    @keydown.space.prevent="$wire.selectField({{ $field->id }})"
                                    aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                                >
                                    <button
                                        type="button"
                                        class="drag-handle mt-0.5 cursor-grab text-gray-400 hover:text-gray-700 shrink-0"
                                        title="Drag to reorder"
                                        aria-label="Drag to reorder"
                                        wire:click.stop
                                        @click.stop
                                    >☰</button>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                                {{ $isExpanded ? '▼' : '' }} {{ $typeLabel }}
                                            </span>
                                            @if ((int) $field->step > 1)
                                                <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">Step {{ $field->step }}</span>
                                            @endif
                                            @if ($type !== 'section' && $field->is_required)
                                                <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">Required</span>
                                            @endif
                                        </div>
                                        <p class="font-medium text-gray-900 truncate mt-0.5">{{ $field->label }}</p>
                                        @if ($type !== 'section')
                                            <p class="text-xs text-gray-500 font-mono truncate">Key: {{ $field->name }}</p>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap gap-1 shrink-0" @click.stop>
                                        @unless ($isExpanded)
                                            <button type="button" wire:click.stop="selectField({{ $field->id }})" class="text-xs text-indigo-700 px-2 py-1 border rounded hover:bg-indigo-50">Edit</button>
                                        @endunless
                                        <button type="button" wire:click.stop="duplicateField({{ $field->id }})" class="text-xs text-gray-700 px-2 py-1 border rounded hover:bg-gray-50">Duplicate</button>
                                        <button type="button" wire:click.stop="deleteField({{ $field->id }})" class="text-xs text-red-700 px-2 py-1 border rounded hover:bg-red-50">Delete</button>
                                    </div>
                                </div>

                                {{-- Expanded property panel --}}
                                @if ($isExpanded)
                                    <div class="border-t border-gray-100 px-3 sm:px-4 pb-4 pt-3 space-y-3 w-full" wire:click.stop @keydown.stop>
                                        @if ($type === 'section')
                                            <label class="block text-xs font-medium text-gray-500">Title
                                                <input type="text" value="{{ $field->label }}" wire:change="updateInline({{ $field->id }}, 'label', $event.target.value)" class="mt-1 w-full text-lg font-semibold rounded border-gray-300">
                                            </label>
                                            <label class="block text-xs font-medium text-gray-500">Description
                                                <input type="text" value="{{ $field->help_text }}" wire:change="updateInline({{ $field->id }}, 'help_text', $event.target.value)" class="mt-1 w-full rounded border-gray-300 text-sm" placeholder="Optional section description">
                                            </label>
                                            <label class="block text-xs font-medium text-gray-500">Step
                                                <input type="number" min="1" value="{{ $field->step }}" wire:change="updateInline({{ $field->id }}, 'step', $event.target.value)" class="mt-1 w-full max-w-[8rem] rounded border-gray-300 text-sm">
                                            </label>
                                        @else
                                            <div class="grid sm:grid-cols-2 gap-2">
                                                <label class="block text-xs font-medium text-gray-500">Label
                                                    <input type="text" value="{{ $field->label }}" wire:change="updateInline({{ $field->id }}, 'label', $event.target.value)" class="mt-1 w-full font-medium rounded border-gray-300 text-sm">
                                                </label>
                                                <label class="block text-xs font-medium text-gray-500">Key
                                                    <input type="text" value="{{ $field->name }}" wire:change="updateInline({{ $field->id }}, 'name', $event.target.value)" class="mt-1 w-full font-mono rounded border-gray-300 text-sm">
                                                </label>
                                                <label class="block text-xs font-medium text-gray-500">Placeholder
                                                    <input type="text" value="{{ $field->placeholder }}" wire:change="updateInline({{ $field->id }}, 'placeholder', $event.target.value)" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                </label>
                                                <label class="block text-xs font-medium text-gray-500">Help text
                                                    <input type="text" value="{{ $field->help_text }}" wire:change="updateInline({{ $field->id }}, 'help_text', $event.target.value)" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                </label>
                                                <label class="block text-xs font-medium text-gray-500">Default value
                                                    <input type="text" value="{{ $field->default_value }}" wire:change="updateInline({{ $field->id }}, 'default_value', $event.target.value)" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                </label>
                                                <label class="block text-xs font-medium text-gray-500">Step
                                                    <input type="number" min="1" value="{{ $field->step }}" wire:change="updateInline({{ $field->id }}, 'step', $event.target.value)" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                </label>
                                            </div>

                                            <label class="inline-flex items-center gap-2 text-sm">
                                                <input type="checkbox" @checked($field->is_required) wire:change="updateInline({{ $field->id }}, 'is_required', $event.target.checked)">
                                                Required
                                            </label>

                                            @if ($hasOptions)
                                                <div x-data="{ open: false }" class="rounded border border-gray-200 bg-gray-50">
                                                    <button type="button" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wide" @click="open = !open" :aria-expanded="open">
                                                        <span>Options</span>
                                                        <span x-text="open ? '▲ Collapse' : '▼ Expand'"></span>
                                                    </button>
                                                    <div x-show="open" x-cloak class="px-3 pb-3">
                                                        <textarea
                                                            rows="3"
                                                            wire:change="updateOptions({{ $field->id }}, $event.target.value)"
                                                            class="w-full rounded border-gray-300 text-sm"
                                                            placeholder="One option per line"
                                                        >{{ implode("\n", $field->field_options ?? []) }}</textarea>
                                                    </div>
                                                </div>
                                            @endif

                                            <div
                                                class="rounded border border-gray-200 bg-gray-50"
                                                x-data="{
                                                    open: false,
                                                    editor: {
                                                        min: @js($rulesEditor['min'] ?? ''),
                                                        max: @js($rulesEditor['max'] ?? ''),
                                                        minLength: @js($rulesEditor['minLength'] ?? ''),
                                                        maxLength: @js($rulesEditor['maxLength'] ?? ''),
                                                        numeric: @js((bool) ($rulesEditor['numeric'] ?? false)),
                                                        email: @js((bool) ($rulesEditor['email'] ?? false)),
                                                        url: @js((bool) ($rulesEditor['url'] ?? false)),
                                                        regex: @js($rulesEditor['regex'] ?? ''),
                                                        fileType: @js($rulesEditor['fileType'] ?? ''),
                                                        fileSize: @js($rulesEditor['fileSize'] ?? ''),
                                                    },
                                                    save() {
                                                        $wire.updateValidationEditor({{ $field->id }}, this.editor);
                                                    }
                                                }"
                                            >
                                                <button type="button" class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wide" @click="open = !open" :aria-expanded="open">
                                                    <span>Validation Rules</span>
                                                    <span x-text="open ? '▲ Collapse' : '▼ Expand'"></span>
                                                </button>
                                                <div x-show="open" x-cloak class="px-3 pb-3 space-y-2">
                                                    <div class="grid sm:grid-cols-2 gap-2">
                                                        <label class="block text-xs text-gray-500">Min
                                                            <input type="number" x-model="editor.min" @change="save()" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-500">Max
                                                            <input type="number" x-model="editor.max" @change="save()" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-500">Min length
                                                            <input type="number" min="0" x-model="editor.minLength" @change="save()" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-500">Max length
                                                            <input type="number" min="0" x-model="editor.maxLength" @change="save()" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-500 sm:col-span-2">Regex
                                                            <input type="text" x-model="editor.regex" @change="save()" placeholder="^[A-Z]+$" class="mt-1 w-full rounded border-gray-300 text-sm font-mono">
                                                        </label>
                                                        <label class="block text-xs text-gray-500">File type (comma-separated)
                                                            <input type="text" x-model="editor.fileType" @change="save()" placeholder="pdf,png,jpg" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                        </label>
                                                        <label class="block text-xs text-gray-500">File size (KB)
                                                            <input type="number" min="1" x-model="editor.fileSize" @change="save()" class="mt-1 w-full rounded border-gray-300 text-sm">
                                                        </label>
                                                    </div>
                                                    <div class="flex flex-wrap gap-4 text-sm pt-1">
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="checkbox" x-model="editor.numeric" @change="save()"> Numeric
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="checkbox" x-model="editor.email" @change="save()"> Email
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="checkbox" x-model="editor.url" @change="save()"> URL
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="pt-1">
                                            <button type="button" wire:click="collapseField" class="text-xs text-gray-600 px-2 py-1 border rounded hover:bg-gray-50">Done</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($fields->isEmpty())
                        <p class="pointer-events-none absolute inset-0 flex items-center justify-center text-gray-500 text-sm">Drop a field here, or click a type in the palette.</p>
                    @endif
                </div>
            </section>
        </div>
    @endif

    @if ($tab === 'schema')
        <div class="bg-white shadow rounded p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Raw JSON schema</h2>
                    <p class="text-sm text-gray-500">Single source of truth. Apply to sync the canvas. Rejects invalid JSON, duplicate keys, and unsupported types.</p>
                </div>
                <button type="button" wire:click="applySchema" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">Apply Schema</button>
            </div>
            <textarea wire:model="schemaJson" rows="24" class="w-full font-mono text-xs rounded border-gray-300"></textarea>
        </div>
    @endif

    @if ($showAiPanel)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @if ($aiJobLogId) wire:poll.2s="pollAiEditJob" @endif>
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeAiPanel"></div>
            <div class="relative w-full max-w-xl max-h-[90vh] overflow-y-auto bg-white rounded-lg shadow-xl p-6 space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-bold">Edit with AI</h2>
                        <p class="text-sm text-gray-500">Describe changes to this form. Generation runs in the queue — preview before applying.</p>
                    </div>
                    <button type="button" wire:click="closeAiPanel" class="text-gray-400 hover:text-gray-700 text-xl">&times;</button>
                </div>
                <textarea wire:model="aiPrompt" rows="4" placeholder="Add emergency contact section. Make phone required. Translate labels to Hindi." class="w-full rounded border-gray-300" @disabled($aiGenerating)></textarea>
                @error('aiPrompt') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="generateAiEdit" wire:loading.attr="disabled" @disabled($aiGenerating) class="px-4 py-2 bg-violet-600 text-white font-semibold rounded-md border-2 border-violet-800 disabled:opacity-50">
                        <span wire:loading.remove wire:target="generateAiEdit">{{ $aiGenerating ? 'Queued…' : 'Generate Preview' }}</span>
                        <span wire:loading wire:target="generateAiEdit">Queueing…</span>
                    </button>
                    @if ($aiQueueStatus)
                        <span class="text-sm text-gray-600 capitalize">Status: {{ $aiQueueStatus }}</span>
                    @endif
                </div>
                @if ($aiPreview)
                    <div class="border rounded p-4 space-y-2">
                        <p class="text-xs uppercase text-gray-500">Preview</p>
                        <h3 class="font-semibold">{{ $aiPreview['title'] }}</h3>
                        <p class="text-sm text-gray-600">{{ $aiPreview['description'] ?? '' }}</p>
                        <ul class="text-sm divide-y border rounded">
                            @foreach ($aiPreview['fields'] as $field)
                                <li class="p-2 flex justify-between gap-2">
                                    <span>{{ $field['label'] }} <span class="text-gray-400">({{ $field['type'] }})</span></span>
                                    <span class="text-xs text-gray-400">step {{ $field['step'] ?? 1 }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <button type="button" wire:click="applyAiEdit" class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md border-2 border-indigo-800">Apply Changes</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <x-publish-modal
        :form="$form"
        :show="$showPublishModal"
        :embed-width="$embedWidth"
        :embed-height="$embedHeight"
    />
</div>
