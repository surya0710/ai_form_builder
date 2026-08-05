<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <p>{{ __("You're logged in!") }}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('forms.index') }}" class="inline-flex px-4 py-2 bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700">My Forms</a>
                        <a href="{{ route('forms.create') }}" class="inline-flex px-4 py-2 border-2 border-indigo-600 text-indigo-700 font-semibold rounded-md hover:bg-indigo-50">Create Form</a>
                        <a href="{{ route('forms.import') }}" class="inline-flex px-4 py-2 border-2 border-gray-300 text-gray-700 font-semibold rounded-md hover:bg-gray-50">Import Form</a>
                        <a href="{{ route('forms.ai') }}" class="inline-flex px-4 py-2 border-2 border-violet-600 text-violet-700 font-semibold rounded-md hover:bg-violet-50">Generate with AI</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
