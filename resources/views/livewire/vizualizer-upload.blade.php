{{-- Upload EDI pro samostatný Visualizer – drag-and-drop přes .upload-zone,
     sjednoceno s podáním hlášení (livewire/prihlaska.blade.php). --}}
<div class="card">
    <div class="px-5 py-4">
        <div wire:loading.remove wire:target="upload">
            <label class="upload-zone" id="viz-edi-zone">
                <input
                    type="file" id="viz-edi-file" accept=".edi,.txt" class="sr-only"
                    wire:model="upload" data-file-zone="viz-edi-zone" data-file-name="viz-edi-name"
                >
                <svg class="upload-zone-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                </svg>
                <span id="viz-edi-name" class="upload-zone-name">{{ __('pages.vizualizer.upload_label') }}…</span>
                <span class="upload-zone-hint">{{ __('pages.vizualizer.upload_hint', ['max' => \App\Support\VkvpaSettings::ediMaxSizeKb()]) }}</span>
            </label>
        </div>

        <div wire:loading wire:target="upload" class="flex items-center gap-2 py-4 text-sm text-muted">
            <svg class="h-4 w-4 animate-spin text-brand" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="10"/>
            </svg>
            {{ __('pages.hlaseni.processing') }}
        </div>

        @if ($errorMessage !== '')
            <div class="field-error mt-3">{{ $errorMessage }}</div>
            @if ($lineErrors !== [])
                <ul class="mt-2 list-disc pl-5 text-sm text-muted">
                    @foreach ($lineErrors as $le)
                        <li><code>{{ $le }}</code></li>
                    @endforeach
                </ul>
            @endif
        @endif

        @error('upload')<span class="field-error mt-2 block">{{ $message }}</span>@enderror
    </div>
</div>
