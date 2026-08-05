<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Edit {{ $form->title }}</h1>
    <form method="POST" action="{{ route('forms.update', $form) }}" class="bg-white p-6 rounded shadow space-y-4">
        @csrf
        @method('PUT')
        <label class="block">
            Title
            <input name="title" value="{{ old('title', $form->title) }}" class="mt-1 w-full rounded border-gray-300" required>
        </label>
        <label class="block">
            Description
            <textarea name="description" class="mt-1 w-full rounded border-gray-300">{{ old('description', $form->description) }}</textarea>
        </label>
        <label class="block">
            Status
            <select name="status" class="mt-1 w-full rounded border-gray-300">
                @foreach (\App\Enums\FormStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($form->status === $status)>{{ ucfirst($status->value) }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">
            Save changes
        </button>
    </form>
</div>
