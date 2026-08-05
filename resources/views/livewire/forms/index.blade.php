<div class="max-w-6xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">My Forms</h1>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('forms.ai') }}" class="px-4 py-2 border-2 border-violet-600 text-violet-700 font-semibold rounded-md hover:bg-violet-50">Generate with AI</a>
            <a href="{{ route('forms.import') }}" class="px-4 py-2 border-2 border-indigo-600 text-indigo-700 font-semibold rounded-md hover:bg-indigo-50">Import Form</a>
            <a href="{{ route('forms.create') }}" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">Create Form</a>
        </div>
    </div>
    @if (session('status')) <p class="mb-4 text-green-700">{{ session('status') }}</p> @endif
    <div class="bg-white rounded shadow divide-y">@forelse ($forms as $form)<a href="{{ route('forms.show', $form) }}" class="block p-4 hover:bg-gray-50"><div class="font-semibold">{{ $form->title }}</div><div class="text-sm text-gray-500">{{ $form->status->value }} · {{ $form->fields_count }} fields · {{ $form->submissions_count }} submissions</div></a>@empty <p class="p-6 text-gray-500">Create your first form to get started.</p>@endforelse</div>
</div>
