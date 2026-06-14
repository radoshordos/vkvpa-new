{{-- Jednotné podání hlášení: výběr → (EDI náhled | ruční formulář) → Odeslat. --}}
<div>
    @if ($errorMessage)
        <x-alert type="error" class="mb-4">
            {{ $errorMessage }}
            @foreach ($lineErrors as $le)
                <br><span class="font-normal text-sm">{{ __('pages.hlaseni.error_line') }}: {{ $le }}</span>
            @endforeach
        </x-alert>
    @endif

    {{-- ═══ VÝBĚR: nahrát EDI / nemám EDI ═══ --}}
    @if ($mode === 'choose')
        <div class="card mb-6">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-soft">
                    <x-icon name="file" class="h-5 w-5 text-brand" />
                </div>
                <p class="text-sm font-semibold text-heading">{{ __('pages.hlaseni.heading_edi') }}</p>
            </div>

            <div class="px-5 py-4">
                <div wire:loading.remove wire:target="upload">
                    <label class="upload-zone" id="edi-zone">
                        <input
                            type="file" id="edi-file" accept=".edi,.txt" class="sr-only"
                            wire:model="upload" data-file-zone="edi-zone" data-file-name="edi-name"
                        >
                        <svg class="upload-zone-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                        </svg>
                        <span id="edi-name" class="upload-zone-name">{{ __('pages.hlaseni.tab_edi') }}…</span>
                        <span class="upload-zone-hint">{{ __('pages.hlaseni.edi_info') }}</span>
                    </label>
                </div>

                <div wire:loading wire:target="upload" class="flex items-center gap-2 py-4 text-sm text-muted">
                    <svg class="h-4 w-4 animate-spin text-brand" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="10"/>
                    </svg>
                    Zpracovávám deník…
                </div>

                @error('upload')<span class="field-error mt-2 block">{{ $message }}</span>@enderror

                <div class="mt-4 border-t border-line pt-4">
                    <button type="button" wire:click="rucne" class="link-arrow">
                        {{ __('pages.hlaseni.no_edi_link') }}
                        <x-icon name="arrow-right" />
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ EDI NÁHLED: zkontrolovat a doplnit kontakt ═══ --}}
    @if ($mode === 'edi-review')
        <div class="card mb-6">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-soft">
                    <x-icon name="file" class="h-5 w-5 text-brand" />
                </div>
                <p class="text-sm font-semibold text-heading">Zkontrolujte načtený deník</p>
            </div>

            <div class="p-5">
                {{-- Přehled deníku (jen ke čtení – body se počítají z lokátorů) --}}
                <dl class="grid grid-cols-2 gap-x-5 gap-y-3 sm:grid-cols-4">
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_callsign') }}</dt>
                        <dd class="mono font-bold text-heading">{{ $pcall }}{{ $qrp ? ' /QRP' : '' }}</dd>
                    </div>
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_locator') }}</dt>
                        <dd class="mono text-heading">{{ $locator }}</dd>
                    </div>
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_period') }}</dt>
                        <dd class="text-heading">{{ $koloNazev }}</dd>
                    </div>
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_category') }}</dt>
                        <dd class="text-heading">{{ $kategorieNazev }}</dd>
                    </div>
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_qso') }}</dt>
                        <dd class="font-bold text-heading">{{ $pocetView }}</dd>
                    </div>
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_mult') }}</dt>
                        <dd class="font-bold text-heading">{{ $nasobiceView }}</dd>
                    </div>
                    <div>
                        <dt class="label">{{ __('pages.hlaseni.field_total') }}</dt>
                        <dd class="font-bold text-heading">{{ number_format($bodyView, 0, ',', "\u{00a0}") }}</dd>
                    </div>
                </dl>

                @if ($warnings)
                    <x-alert type="warning" class="mt-4">
                        <strong>{{ __('pages.hlaseni.import_warnings') }}</strong>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($warnings as $w)
                                <li class="font-normal">{{ $w }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                {{-- Rozpad spojení (co se započítalo a proč) – sbalitelné --}}
                @if ($report)
                    <details class="mt-4 rounded-lg border border-line p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-heading">
                            Rozpad spojení – co se započítalo a proč ({{ $report->pocet }}/{{ $report->parsedCount }} QSO)
                        </summary>
                        <div class="mt-3">
                            @include('partials.edi-rozpad', ['report' => $report])
                        </div>
                    </details>
                @endif

                {{-- Původní a redukovaný (EDIR) soubor – sbalitelné --}}
                @if ($ediLines !== [])
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <details class="rounded-lg border border-line p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-heading">
                                EDI soubor
                                <span class="ml-1 font-normal text-xs text-muted">(červeně řádky, které EDIR ořízne – mimo okno)</span>
                            </summary>
                            <div class="mt-2 max-h-80 overflow-auto rounded bg-surface-2 p-2 font-mono text-xs leading-relaxed">
                                @foreach ($ediLines as $l)<div class="whitespace-pre" style="{{ $l['dropped'] ? 'background-color:var(--danger-soft);color:var(--danger);text-decoration:line-through' : '' }}">{{ $l['text'] === '' ? ' ' : $l['text'] }}</div>@endforeach
                            </div>
                        </details>
                        <details class="rounded-lg border border-line p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-heading">EDIR – oříznutý na závodní okno (08:00–11:00 UTC)</summary>
                            <pre class="mt-2 max-h-80 overflow-auto rounded bg-surface-2 p-2 text-xs">{{ $ediReduced }}</pre>
                        </details>
                    </div>
                @endif

                {{-- Kontaktní údaje – editovatelné --}}
                <div class="mt-5 border-t border-line pt-4">
                    @include('livewire.partials.prihlaska-kontakt')
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <button type="button" wire:click="zpet" class="text-sm">{{ __('pages.hlaseni.btn_clear') }}</button>
                    <button type="button" wire:click="odeslat" wire:loading.attr="disabled" class="btn btn-primary">
                        {{ __('pages.hlaseni.btn_send') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ RUČNÍ FORMULÁŘ ═══ --}}
    @if ($mode === 'manual')
        <div class="card mb-6">
            <div class="flex items-center gap-3 border-b border-line px-5 py-4">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-soft">
                    <x-icon name="file" class="h-5 w-5 text-brand" />
                </div>
                <p class="text-sm font-semibold text-heading">{{ __('pages.hlaseni.heading_manual') }}</p>
            </div>

            <div class="p-5">
                <div class="grid gap-x-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="f-kolo">{{ __('pages.hlaseni.field_period') }} *</label>
                        <select id="f-kolo" wire:model="kolo" @class(['select', 'input-err' => $errors->has('kolo')])>
                            <option value="0">{{ __('pages.hlaseni.select_period') }}</option>
                            @foreach ($kola as $k)
                                <option value="{{ $k->id }}">{{ $k->nazev }}</option>
                            @endforeach
                        </select>
                        @error('kolo')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="label" for="f-kategorie">{{ __('pages.hlaseni.field_category') }} *</label>
                        <select id="f-kategorie" wire:model="kategorie" @class(['select', 'input-err' => $errors->has('kategorie')])>
                            <option value="0">{{ __('pages.hlaseni.select_category') }}</option>
                            @foreach ($kategorieList as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nazev }}</option>
                            @endforeach
                        </select>
                        @error('kategorie')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="label" for="f-znacka">{{ __('pages.hlaseni.field_callsign') }} *</label>
                        <input id="f-znacka" wire:model="znacka" @class(['input mono font-bold', 'input-err' => $errors->has('znacka')])>
                        @error('znacka')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="label" for="f-locator">{{ __('pages.hlaseni.field_locator') }} *</label>
                        <input id="f-locator" wire:model="locator" @class(['input mono', 'input-err' => $errors->has('locator')])>
                        @error('locator')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <label class="mb-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="qrp" value="1"> {{ __('pages.hlaseni.field_qrp') }}
                </label>
                <label class="mb-4 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="lp" value="1"> {{ __('pages.hlaseni.field_lp') }}
                </label>

                <div class="grid grid-cols-2 gap-x-5 sm:grid-cols-4">
                    <div class="field">
                        <label class="label" for="f-pocet">{{ __('pages.hlaseni.field_qso') }} *</label>
                        <input id="f-pocet" type="number" min="0" wire:model="pocet" @class(['input num', 'input-err' => $errors->has('pocet')])>
                        @error('pocet')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="f-bodu">{{ __('pages.hlaseni.field_qso_pts') }}</label>
                        <input id="f-bodu" type="number" min="0" wire:model="bodu_za_qso" class="input num">
                    </div>
                    <div class="field">
                        <label class="label" for="f-nasobice">{{ __('pages.hlaseni.field_mult') }} *</label>
                        <input id="f-nasobice" type="number" min="0" wire:model="nasobice" class="input num">
                    </div>
                    <div class="field">
                        <label class="label" for="f-body">{{ __('pages.hlaseni.field_total') }} *</label>
                        <input id="f-body" type="number" min="0" wire:model="body" class="input num font-bold">
                    </div>
                </div>

                @include('livewire.partials.prihlaska-kontakt')

                <div class="mt-4 flex items-center justify-between">
                    <button type="button" wire:click="zpet" class="text-sm">{{ __('pages.hlaseni.btn_clear') }}</button>
                    <button type="button" wire:click="odeslat" wire:loading.attr="disabled" class="btn btn-primary">
                        {{ __('pages.hlaseni.btn_send') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
