@props([
    'form',
    'show' => false,
    'embedWidth' => '100%',
    'embedHeight' => 800,
    'closeMethod' => 'closePublishModal',
])

@if ($show)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="publish-modal-title"
        x-data="{
            copied: null,
            async copy(text, key) {
                try {
                    await navigator.clipboard.writeText(text);
                    this.copied = key;
                    setTimeout(() => { if (this.copied === key) this.copied = null }, 2000);
                } catch (e) {
                    const area = document.createElement('textarea');
                    area.value = text;
                    document.body.appendChild(area);
                    area.select();
                    document.execCommand('copy');
                    document.body.removeChild(area);
                    this.copied = key;
                    setTimeout(() => { if (this.copied === key) this.copied = null }, 2000);
                }
            }
        }"
    >
        <div class="absolute inset-0 bg-gray-900/50" wire:click="{{ $closeMethod }}"></div>

        <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-lg shadow-xl p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="publish-modal-title" class="text-xl font-bold text-gray-900">Form Published Successfully</h2>
                    <p class="text-sm text-gray-500 mt-1">Share the public link or embed this form on your website.</p>
                </div>
                <button type="button" wire:click="{{ $closeMethod }}" class="text-gray-400 hover:text-gray-600 text-xl leading-none" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">Public URL</label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $form->publicUrl() }}"
                        class="flex-1 rounded border-gray-300 bg-gray-50 text-sm"
                    >
                    <button
                        type="button"
                        @click="copy(@js($form->publicUrl()), 'url')"
                        class="px-3 py-2 text-sm bg-indigo-600 text-white font-semibold border-2 border-indigo-800 rounded-md shadow-sm hover:bg-indigo-700 whitespace-nowrap"
                    >
                        <span x-text="copied === 'url' ? 'Copied!' : 'Copy Link'"></span>
                    </button>
                </div>
                <a href="{{ $form->publicUrl() }}" target="_blank" class="inline-block text-sm text-indigo-600 hover:underline">
                    Open Public Form
                </a>
            </div>

            <hr class="border-gray-200">

            <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700">Embed Code</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-xs text-gray-600">
                        Width
                        <input type="text" wire:model.live="embedWidth" class="mt-1 w-full rounded border-gray-300 text-sm">
                    </label>
                    <label class="block text-xs text-gray-600">
                        Height
                        <input type="number" wire:model.live="embedHeight" min="200" class="mt-1 w-full rounded border-gray-300 text-sm">
                    </label>
                </div>
                <textarea
                    readonly
                    rows="6"
                    class="w-full rounded border-gray-300 bg-gray-50 text-xs font-mono"
                >{{ $form->embedCode($embedWidth, $embedHeight) }}</textarea>
                <button
                    type="button"
                    @click="copy(@js($form->embedCode($embedWidth, $embedHeight)), 'embed')"
                    class="px-3 py-2 text-sm border-2 border-indigo-600 text-indigo-700 font-semibold rounded-md hover:bg-indigo-50"
                >
                    <span x-text="copied === 'embed' ? 'Copied!' : 'Copy Embed'"></span>
                </button>
            </div>

            <hr class="border-gray-200">

            <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">JavaScript Embed <span class="font-normal text-gray-400">(optional)</span></label>
                <textarea
                    readonly
                    rows="4"
                    class="w-full rounded border-gray-300 bg-gray-50 text-xs font-mono"
                >{{ $form->embedScript() }}</textarea>
                <p class="text-xs text-gray-500">Future enhancement: the script will automatically render the form.</p>
                <button
                    type="button"
                    @click="copy(@js($form->embedScript()), 'script')"
                    class="px-3 py-2 text-sm border border-gray-300 text-gray-700 font-semibold rounded-md hover:bg-gray-50"
                >
                    <span x-text="copied === 'script' ? 'Copied!' : 'Copy Script'"></span>
                </button>
            </div>

            <hr class="border-gray-200">

            <div class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">API Endpoint</label>
                <code class="block rounded bg-gray-50 border border-gray-200 px-3 py-2 text-xs text-gray-700 break-all">
                    GET {{ $form->publicApiUrl() }}
                </code>
            </div>

            @php($analytics = $form->analytics())
            <div class="rounded border border-dashed border-gray-200 bg-gray-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Analytics (coming soon)</p>
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                    <div>Views: <span class="font-medium">{{ $analytics['views'] }}</span></div>
                    <div>Submissions: <span class="font-medium">{{ $analytics['submissions'] }}</span></div>
                    <div>Conversion: <span class="font-medium">{{ $analytics['conversion_rate'] !== null ? $analytics['conversion_rate'].'%' : '—' }}</span></div>
                    <div>Last submission: <span class="font-medium">{{ $analytics['last_submission_at'] ? \Illuminate\Support\Carbon::parse($analytics['last_submission_at'])->diffForHumans() : '—' }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endif
