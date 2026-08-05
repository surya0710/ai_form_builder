<div class="max-w-3xl mx-auto p-6" @if ($jobLogId) wire:poll.2s="pollJob" @endif>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Generate Form using AI</h1>
            <p class="text-sm text-gray-500 mt-1">Describe the form you want. Generation runs in the queue, then you can preview and save.</p>
        </div>
        <a href="{{ route('forms.index') }}" class="text-indigo-600 text-sm" wire:navigate>Back to forms</a>
    </div>

    @if ($errorMessage)
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errorMessage }}</div>
    @endif

    <div class="bg-white rounded shadow p-6 space-y-4">
        <label class="block">
            <span class="text-sm font-medium text-gray-700">Describe the form you want</span>
            <textarea
                wire:model="prompt"
                rows="5"
                placeholder="Create an employee onboarding form with department, manager, joining date and comments"
                class="mt-2 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                @disabled($generating)
            ></textarea>
        </label>
        @error('prompt')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-2">Examples</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($examples as $example)
                    <button
                        type="button"
                        wire:click="useExample(@js($example))"
                        @disabled($generating)
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        {{ $example }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <button
                type="button"
                wire:click="generate"
                wire:loading.attr="disabled"
                wire:target="generate,regenerate"
                class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="generate,regenerate">Generate</span>
                <span wire:loading wire:target="generate,regenerate">Queuing…</span>
            </button>
        </div>
    </div>

    @if ($generating || $jobLogId)
        <div class="mt-6 flex flex-col items-center justify-center rounded border border-indigo-100 bg-indigo-50 px-6 py-10 text-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent"></div>
            <p class="mt-4 font-semibold text-indigo-900">
                @if ($queueStatus === 'queued') Queued
                @elseif ($queueStatus === 'generating') Generating...
                @else Generating your form...
                @endif
            </p>
            <p class="mt-1 text-sm text-indigo-700">Please wait...</p>
            <p class="mt-2 text-xs uppercase tracking-wide text-indigo-600">Status: {{ $queueStatus ?: 'queued' }}</p>
        </div>
    @endif

    @if ($step === 'preview' && $preview)
        <div class="mt-6 bg-white rounded shadow p-6 space-y-5">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500">Preview · Completed</p>
                <h2 class="text-xl font-semibold mt-1">{{ $preview['title'] }}</h2>
                @if (!empty($preview['description']))
                    <p class="text-gray-600 mt-1">{{ $preview['description'] }}</p>
                @endif
                <p class="text-sm text-gray-500 mt-2">{{ count($preview['fields']) }} field(s)</p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Fields</h3>
                <div class="divide-y border rounded">
                    @foreach ($preview['fields'] as $field)
                        <div class="p-3 flex justify-between gap-4">
                            <div>
                                <div class="font-medium">{{ $field['label'] }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $field['type'] }}
                                    @if (!empty($field['is_required'])) · required @endif
                                    @if (!empty($field['field_options']))
                                        · {{ implode(', ', $field['field_options']) }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-xs text-gray-400">{{ $field['name'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="saveForm" wire:loading.attr="disabled" wire:target="saveForm" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveForm">Save Form</span>
                    <span wire:loading wire:target="saveForm">Saving…</span>
                </button>
                <button type="button" wire:click="regenerate" class="px-4 py-2 border-2 border-indigo-600 text-indigo-700 font-semibold rounded-md hover:bg-indigo-50">Regenerate</button>
                <button type="button" wire:click="resetPrompt" class="px-4 py-2 border-2 border-gray-300 text-gray-700 font-semibold rounded-md hover:bg-gray-50">Edit prompt</button>
            </div>
        </div>
    @endif
</div>
