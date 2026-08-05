<div class="max-w-3xl mx-auto p-6">
    <div class="mb-6">
        <a href="{{ route('forms.submissions', $form) }}" class="text-sm text-indigo-600" wire:navigate>← Back to submissions</a>
        <h1 class="text-2xl font-bold mt-2">Submission</h1>
        <p class="text-sm text-gray-500">{{ $submission->uuid }} · {{ optional($submission->submitted_at)->toDayDateTimeString() }}</p>
    </div>

    <div class="bg-white rounded shadow divide-y">
        @forelse ($submission->answers as $answer)
            <div class="p-4">
                <p class="text-xs uppercase text-gray-500">{{ $answer->field?->label ?? 'Field' }}</p>
                <p class="mt-1 whitespace-pre-wrap">{{ $answer->answer ?? '—' }}</p>
            </div>
        @empty
            <p class="p-6 text-gray-500">No answers recorded.</p>
        @endforelse
    </div>
</div>
