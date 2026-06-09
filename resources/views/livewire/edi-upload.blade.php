{{-- Livewire komponenta pro nahrání EDI deníku. --}}
<div>
  {{-- ── IDLE / UPLOADING ─────────────────────────────── --}}
  @if ($state !== 'success')
    <div class="card p-4">
      <label class="label block mb-2" for="edi-file">EDI soubor / EDI file</label>

      <div wire:loading.remove wire:target="upload">
        <input
          id="edi-file"
          type="file"
          accept=".edi,.txt"
          wire:model="upload"
          class="text-sm"
        >
        <p class="text-xs text-muted mt-1">Formát EDI (REG1TEST), max 10 MB.</p>
      </div>

      <div wire:loading wire:target="upload" class="flex items-center gap-2 text-sm text-muted py-2">
        <svg class="h-4 w-4 animate-spin text-brand" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="10"/>
        </svg>
        Zpracovávám deník…
      </div>

      @error('upload')
        <x-alert type="error" class="mt-3">{{ $message }}</x-alert>
      @enderror

      @if ($state === 'error')
        <x-alert type="error" class="mt-3">
          {{ $errorMessage }}
          @foreach ($lineErrors as $le)
            <br><span class="font-normal text-sm">Chybný řádek: {{ $le }}</span>
          @endforeach
        </x-alert>
        <button wire:click="resetForm" class="btn btn-ghost btn-sm mt-2">Zkusit znovu</button>
      @endif
    </div>
  @endif

  {{-- ── SUCCESS ─────────────────────────────────────── --}}
  @if ($state === 'success')
    <div class="card p-4 border-green-500/40 bg-green-50 dark:bg-green-950/20">
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-green-600" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
        </svg>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-heading">Deník nahrán: {{ $pcall }}</p>
          <p class="text-sm text-muted mt-0.5">{{ $band }} · {{ $pocet }} QSO · {{ number_format($body, 0, ',', "\u{00a0}") }} bodů</p>

          @if ($warnings)
            <div class="mt-2 text-sm text-amber-700 dark:text-amber-400">
              <p class="font-medium mb-1">Upozornění k deníku:</p>
              <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($warnings as $w)
                  <li>{{ $w }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
      </div>

      <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('hlaseni.index') }}" class="btn btn-primary">
          Pokračovat na formulář hlášení →
        </a>
        <button wire:click="resetForm" class="btn btn-ghost btn-sm">Nahrát jiný deník</button>
      </div>
    </div>
  @endif
</div>
