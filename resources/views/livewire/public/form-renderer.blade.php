<main class="max-w-2xl mx-auto p-5 md:py-12">
    @if (session('submission_success') || $submitted)
        <livewire:public.submission-success />
    @else
        @if ($isPreview)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>Preview only.</strong>
                This form is <span class="font-medium">{{ $form->status->value }}</span> and is not accepting public submissions yet.
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6 md:p-8">
            <h1 class="text-2xl font-bold">{{ $form->title }}</h1>
            @if ($form->description)<p class="text-gray-600 mt-2">{{ $form->description }}</p>@endif

            @if ($totalSteps > 1)
                <div class="mt-4 flex items-center gap-2 text-sm text-gray-600">
                    <span class="font-medium text-indigo-700">Step {{ $currentStep }} of {{ $totalSteps }}</span>
                    <div class="flex-1 h-2 rounded bg-gray-100 overflow-hidden">
                        <div class="h-full bg-indigo-600 transition-all" style="width: {{ ($currentStep / $totalSteps) * 100 }}%"></div>
                    </div>
                </div>
            @endif

            <form wire:submit.prevent="{{ $currentStep < $totalSteps ? 'nextStep' : 'submit' }}" class="mt-6 space-y-5">
                @foreach ($stepFields as $field)
                    @php $type = $field->type instanceof \BackedEnum ? $field->type->value : (string) $field->type; @endphp

                    @if ($type === 'section')
                        <div class="pt-2 border-t">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $field->label }}</h2>
                            @if ($field->help_text)<p class="text-sm text-gray-500 mt-1">{{ $field->help_text }}</p>@endif
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium mb-1" for="{{ $field->name }}">
                                {{ $field->label }}
                                @if ($field->is_required)<span class="text-red-600">*</span>@endif
                            </label>

                            @if ($type === 'textarea')
                                <textarea id="{{ $field->name }}" wire:model="answers.{{ $field->name }}" placeholder="{{ $field->placeholder }}" @disabled($isPreview) class="w-full rounded border-gray-300 disabled:bg-gray-50"></textarea>
                            @elseif ($type === 'select')
                                <select id="{{ $field->name }}" wire:model="answers.{{ $field->name }}" @disabled($isPreview) class="w-full rounded border-gray-300 disabled:bg-gray-50">
                                    <option value="">Select an option</option>
                                    @foreach ($field->field_options ?? [] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            @elseif (in_array($type, ['radio', 'checkbox'], true))
                                <div class="space-y-2">
                                    @foreach ($field->field_options ?? [] as $option)
                                        <label class="block">
                                            @if ($type === 'checkbox')
                                                <input type="checkbox" value="{{ $option }}" wire:model="answers.{{ $field->name }}" @disabled($isPreview)> {{ $option }}
                                            @else
                                                <input type="radio" value="{{ $option }}" wire:model="answers.{{ $field->name }}" @disabled($isPreview)> {{ $option }}
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($type === 'rating')
                                @php $max = (int) ($field->settings['max'] ?? 5); @endphp
                                <div class="flex gap-1" role="radiogroup" aria-label="{{ $field->label }}">
                                    @for ($i = 1; $i <= $max; $i++)
                                        <button
                                            type="button"
                                            wire:click="$set('answers.{{ $field->name }}', {{ $i }})"
                                            @disabled($isPreview)
                                            class="text-2xl leading-none {{ (int) ($answers[$field->name] ?? 0) >= $i ? 'text-amber-500' : 'text-gray-300' }}"
                                            aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }}"
                                        >★</button>
                                    @endfor
                                </div>
                            @elseif ($type === 'file')
                                <input id="{{ $field->name }}" type="file" wire:model="answers.{{ $field->name }}" @disabled($isPreview) class="w-full text-sm">
                            @else
                                @php
                                    $inputType = match ($type) {
                                        'phone' => 'tel',
                                        'datetime' => 'datetime-local',
                                        default => $type,
                                    };
                                @endphp
                                <input id="{{ $field->name }}" type="{{ $inputType }}" wire:model="answers.{{ $field->name }}" placeholder="{{ $field->placeholder }}" @disabled($isPreview) class="w-full rounded border-gray-300 disabled:bg-gray-50">
                            @endif

                            @if ($field->help_text)<p class="text-xs text-gray-500 mt-1">{{ $field->help_text }}</p>@endif
                            @error('answers.'.$field->name)<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif
                @endforeach

                <div class="flex gap-3 pt-2">
                    @if ($currentStep > 1)
                        <button type="button" wire:click="previousStep" class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 font-semibold rounded-md hover:bg-gray-50">Previous</button>
                    @endif

                    @if ($currentStep < $totalSteps)
                        <button type="submit" class="flex-1 px-4 py-3 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">Next</button>
                    @else
                        <button type="submit" @disabled($isPreview) class="flex-1 px-4 py-3 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ $isPreview ? 'Submit (disabled in preview)' : 'Submit' }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif
</main>
