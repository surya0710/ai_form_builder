<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Create Form</h1>
    <form method="POST" action="{{ route('forms.store') }}" class="bg-white p-6 rounded shadow space-y-4">
        @csrf
        <label class="block">
            Title
            <input name="title" value="{{ old('title') }}" class="mt-1 w-full rounded border-gray-300" required>
        </label>
        <label class="block">
            Description
            <textarea name="description" class="mt-1 w-full rounded border-gray-300">{{ old('description') }}</textarea>
        </label>
        <input type="hidden" name="status" value="draft">
        @error('title')
            <p class="text-red-600 text-sm">{{ $message }}</p>
        @enderror
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">
                Create draft
            </button>
            <a href="{{ route('forms.ai') }}" class="text-sm text-violet-700 hover:underline" wire:navigate>Or generate with AI</a>
        </div>
    </form>
</div>
