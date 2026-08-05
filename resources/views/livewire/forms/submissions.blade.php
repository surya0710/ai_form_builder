<div class="max-w-5xl mx-auto p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Submissions</h1>
            <p class="text-sm text-gray-500 mt-1">
                <a href="{{ route('forms.show', $form) }}" class="text-indigo-600" wire:navigate>{{ $form->title }}</a>
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="exportCsv" class="px-4 py-2 border-2 border-indigo-600 text-indigo-700 font-semibold rounded-md hover:bg-indigo-50">Export CSV</button>
            <a href="{{ route('forms.builder', $form) }}" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md" wire:navigate>Open Builder</a>
        </div>
    </div>

    @if (session('status'))
        <p class="mb-4 text-green-700">{{ session('status') }}</p>
    @endif

    <div class="mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search UUID, IP, or answer…" class="w-full max-w-md rounded border-gray-300">
    </div>

    <div class="bg-white rounded shadow divide-y">
        @forelse ($submissions as $submission)
            <div class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <a href="{{ route('forms.submissions.show', [$form, $submission]) }}" class="font-medium text-indigo-700" wire:navigate>{{ $submission->uuid }}</a>
                    <p class="text-sm text-gray-500">
                        {{ optional($submission->submitted_at)->toDayDateTimeString() ?? '—' }}
                        · {{ $submission->answers_count }} answers
                        @if ($submission->ip_address) · {{ $submission->ip_address }} @endif
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('forms.submissions.show', [$form, $submission]) }}" class="px-3 py-1.5 text-sm border rounded hover:bg-gray-50" wire:navigate>View</a>
                    <button type="button" wire:click="deleteSubmission({{ $submission->id }})" wire:confirm="Delete this submission?" class="px-3 py-1.5 text-sm border border-red-300 text-red-700 rounded hover:bg-red-50">Delete</button>
                </div>
            </div>
        @empty
            <p class="p-6 text-gray-500">No submissions yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $submissions->links() }}</div>
</div>
