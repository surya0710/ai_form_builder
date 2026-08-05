<div class="max-w-4xl mx-auto p-6">
    <div class="flex justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $form->title }}</h1>
            <p class="text-gray-600">{{ $form->description }}</p>
        </div>
        <a href="{{ route('forms.builder', $form) }}" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 h-fit">
            Open Builder
        </a>
    </div>

    @if ($statusMessage)
        <p class="mt-4 text-green-700">{{ $statusMessage }}</p>
    @elseif (session('status'))
        <p class="mt-4 text-green-700">{{ session('status') }}</p>
    @endif

    <div class="mt-6 bg-white rounded shadow p-5">
        <p class="text-sm font-semibold text-gray-500 uppercase">{{ $form->status->value }}</p>
        <p class="mt-3">{{ $form->fields->count() }} field(s)</p>

        @if ($form->status === \App\Enums\FormStatus::Published)
            <div class="mt-4 rounded border border-green-100 bg-green-50 px-4 py-3 text-sm">
                <p class="font-medium text-green-800">Public URL</p>
                <p class="mt-1 break-all text-green-700">{{ $form->publicUrl() }}</p>
            </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
            <a class="px-3 py-2 text-sm border-2 border-indigo-600 text-indigo-700 font-semibold rounded-md hover:bg-indigo-50" href="{{ route('forms.edit', $form) }}">
                Edit details
            </a>

            @if ($form->status !== \App\Enums\FormStatus::Published)
                <button type="button" wire:click="publishForm" class="px-3 py-2 text-sm bg-green-600 text-white font-semibold border-2 border-green-800 rounded-md shadow-sm hover:bg-green-700">
                    Publish
                </button>
            @else
                <button type="button" wire:click="openShareModal" class="px-3 py-2 text-sm bg-green-600 text-white font-semibold border-2 border-green-800 rounded-md shadow-sm hover:bg-green-700">
                    Share / Embed
                </button>
            @endif

            <button type="button" wire:click="archiveForm" class="px-3 py-2 text-sm bg-amber-500 text-white font-semibold border-2 border-amber-700 rounded-md shadow-sm hover:bg-amber-600">
                Archive
            </button>

            <form method="POST" action="{{ route('forms.destroy', $form) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-2 text-sm bg-red-600 text-white font-semibold border-2 border-red-800 rounded-md shadow-sm hover:bg-red-700" onclick="return confirm('Delete this form?')">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <x-publish-modal
        :form="$form"
        :show="$showPublishModal"
        :embed-width="$embedWidth"
        :embed-height="$embedHeight"
    />
</div>
