<main class="max-w-2xl mx-auto p-5 md:py-12">
    @if (session('submission_success'))
        <livewire:public.submission-success />
    @else
        @if ($isPreview)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <strong>Preview only.</strong>
                This form is <span class="font-medium">{{ $form->status->value }}</span> and is not accepting public submissions yet.
                @if ($form->status === \App\Enums\FormStatus::Draft)
                    Publish it to share the public link.
                @endif
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6 md:p-8">
            <h1 class="text-2xl font-bold">{{ $form->title }}</h1>
            @if ($form->description)<p class="text-gray-600 mt-2">{{ $form->description }}</p>@endif
            <form method="POST" enctype="multipart/form-data" action="{{ route('public.forms.submit', $form->uuid) }}" class="mt-6 space-y-5">
                @csrf
                @foreach ($form->fields as $field)
                    <div>
                        <label class="block text-sm font-medium mb-1" for="{{ $field->name }}">{{ $field->label }} @if ($field->is_required)<span class="text-red-600">*</span>@endif</label>
                        @if ($field->type->value === 'textarea')
                            <textarea id="{{ $field->name }}" name="{{ $field->name }}" placeholder="{{ $field->placeholder }}" @disabled($isPreview) class="w-full rounded border-gray-300 disabled:bg-gray-50">{{ old($field->name, $field->default_value) }}</textarea>
                        @elseif ($field->type->value === 'select')
                            <select id="{{ $field->name }}" name="{{ $field->name }}" @disabled($isPreview) class="w-full rounded border-gray-300 disabled:bg-gray-50"><option value="">Select an option</option>@foreach ($field->field_options ?? [] as $option)<option value="{{ $option }}" @selected(old($field->name, $field->default_value) === $option)>{{ $option }}</option>@endforeach</select>
                        @elseif (in_array($field->type->value, ['radio', 'checkbox']))
                            <div class="space-y-2">@foreach ($field->field_options ?? [] as $option)<label class="block"><input type="{{ $field->type->value }}" name="{{ $field->name }}{{ $field->type->value === 'checkbox' ? '[]' : '' }}" value="{{ $option }}" @disabled($isPreview) @checked(in_array($option, (array) old($field->name, $field->default_value ? [$field->default_value] : [])))> {{ $option }}</label>@endforeach</div>
                        @else
                            @php
                                $inputType = match ($field->type->value) {
                                    'phone' => 'tel',
                                    'datetime' => 'datetime-local',
                                    default => $field->type->value,
                                };
                            @endphp
                            <input id="{{ $field->name }}" name="{{ $field->name }}" type="{{ $inputType }}" value="{{ old($field->name, $field->default_value) }}" placeholder="{{ $field->placeholder }}" @disabled($isPreview) class="w-full rounded border-gray-300 disabled:bg-gray-50">
                        @endif
                        @if ($field->help_text)<p class="text-xs text-gray-500 mt-1">{{ $field->help_text }}</p>@endif
                        @error($field->name)<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <button type="submit" @disabled($isPreview) class="w-full px-4 py-3 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ $isPreview ? 'Submit (disabled in preview)' : 'Submit' }}
                </button>
            </form>
        </div>
    @endif
</main>
