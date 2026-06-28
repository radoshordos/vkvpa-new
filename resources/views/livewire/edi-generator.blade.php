{{-- Ruční generátor EDI deníku: vlevo formulář (hlavička + spojení), vpravo
     živý náhled (.edi text, skóre, mapa). Mapová data a stav pro localStorage
     předáváme JS přes data-* atributy (bez inline scriptu kvůli CSP). --}}
<div class="grid grid-cols-1 gap-5 lg:grid-cols-2" data-edi-generator>

  {{-- ── Levý sloupec: formulář ──────────────────────────────────────────── --}}
  <div>
    @if ($errorMessage !== '')
      <x-alert type="error" class="mb-4">{{ $errorMessage }}</x-alert>
    @endif

    {{-- Hlavička deníku --}}
    <div class="card mb-4">
      <div class="px-5 py-4">
        <div class="section-head mb-3">{{ __('pages.generator.header') }}</div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.pcall') }}</span>
            <input type="text" class="input uppercase" maxlength="10"
                   wire:model.live.debounce.500ms="pcall" placeholder="OK1ABC">
            @error('pcall')<span class="field-error mt-1 block">{{ $message }}</span>@enderror
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.pwwlo') }}</span>
            <input type="text" class="input uppercase" maxlength="6"
                   wire:model.live.debounce.500ms="pwwlo" placeholder="JN79">
            @error('pwwlo')<span class="field-error mt-1 block">{{ $message }}</span>@enderror
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.tdate') }}</span>
            <input type="date" class="input" wire:model.live="tdate">
            @error('tdate')<span class="field-error mt-1 block">{{ $message }}</span>@enderror
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.pband') }}</span>
            <select class="select" wire:model.live="pband">
              @foreach ($bands as $b)
                <option value="{{ $b }}">{{ $b }}</option>
              @endforeach
            </select>
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.psect') }}</span>
            <select class="select" wire:model.live="psect">
              <option value="SINGLE">{{ __('pages.generator.single') }}</option>
              <option value="MULTI">{{ __('pages.generator.multi') }}</option>
            </select>
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.spowe') }}</span>
            <input type="text" inputmode="numeric" class="input" maxlength="6"
                   wire:model.live.debounce.500ms="spowe" placeholder="100">
          </label>
        </div>

        <div class="mt-4 section-head mb-3">{{ __('pages.generator.contact') }}</div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.rname') }}</span>
            <input type="text" class="input" maxlength="60" wire:model.live.debounce.500ms="rname">
            @error('rname')<span class="field-error mt-1 block">{{ $message }}</span>@enderror
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.rhbbs') }}</span>
            <input type="email" class="input" maxlength="250" wire:model.live.debounce.500ms="rhbbs">
            @error('rhbbs')<span class="field-error mt-1 block">{{ $message }}</span>@enderror
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.rphon') }}</span>
            <input type="text" class="input" maxlength="20" wire:model.live.debounce.500ms="rphon">
            @error('rphon')<span class="field-error mt-1 block">{{ $message }}</span>@enderror
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.stxeq') }}</span>
            <input type="text" class="input" maxlength="60" wire:model.live.debounce.500ms="stxeq">
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.sante') }}</span>
            <input type="text" class="input" maxlength="60" wire:model.live.debounce.500ms="sante">
          </label>
          <label class="block">
            <span class="block text-xs text-muted mb-1">{{ __('pages.generator.remarks') }}</span>
            <input type="text" class="input" maxlength="250" wire:model.live.debounce.500ms="remarks">
          </label>
        </div>
      </div>
    </div>

    {{-- Spojení --}}
    <div class="card">
      <div class="px-5 py-4">
        <div class="flex items-center justify-between mb-3">
          <div class="section-head">{{ __('pages.generator.qsos') }}</div>
          <button type="button" class="btn btn-sm" wire:click="addQso">+ {{ __('pages.generator.add_qso') }}</button>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th class="num">#</th>
                <th>{{ __('pages.generator.col_time') }}</th>
                <th>{{ __('pages.generator.col_call') }}</th>
                <th>{{ __('pages.generator.col_mode') }}</th>
                <th>{{ __('pages.generator.col_rst_s') }}</th>
                <th>{{ __('pages.generator.col_rst_r') }}</th>
                <th>{{ __('pages.generator.col_wwl') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($qsos as $i => $q)
                <tr wire:key="qso-{{ $i }}">
                  <td class="num text-muted">{{ $i + 1 }}</td>
                  <td><input type="text" inputmode="numeric" maxlength="4" class="qso-input"
                             placeholder="0800" wire:model.live.debounce.500ms="qsos.{{ $i }}.time"></td>
                  <td><input type="text" maxlength="12" class="qso-input uppercase"
                             placeholder="OK1XYZ" wire:model.live.debounce.500ms="qsos.{{ $i }}.call"></td>
                  <td>
                    <select class="qso-input" wire:model.live="qsos.{{ $i }}.mode">
                      @foreach ($modes as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="text" maxlength="4" class="qso-input"
                             wire:model.live.debounce.500ms="qsos.{{ $i }}.rst_s"></td>
                  <td><input type="text" maxlength="4" class="qso-input"
                             wire:model.live.debounce.500ms="qsos.{{ $i }}.rst_r"></td>
                  <td><input type="text" maxlength="6" class="qso-input uppercase"
                             placeholder="JN89" wire:model.live.debounce.500ms="qsos.{{ $i }}.wwl"></td>
                  <td class="text-right">
                    <button type="button" class="btn-ghost btn-sm" title="{{ __('pages.generator.remove_qso') }}"
                            wire:click="removeQso({{ $i }})">✕</button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="text-xs text-muted mt-2">{{ __('pages.generator.qso_hint') }}</p>
      </div>
    </div>
  </div>

  {{-- ── Pravý sloupec: živý náhled ──────────────────────────────────────── --}}
  <div>
    <div class="lg:sticky lg:top-4 space-y-4">

      {{-- Skóre --}}
      <div class="grid grid-cols-3 gap-3">
        <div class="rounded-lg border border-line bg-surface p-3 text-center">
          <div class="text-2xl font-bold text-heading">{{ $score->qsoCount }}</div>
          <div class="text-xs text-muted mt-0.5">{{ __('pages.generator.stat_qso') }}</div>
        </div>
        <div class="rounded-lg border border-line bg-surface p-3 text-center">
          <div class="text-2xl font-bold text-heading">{{ $score->multiplier }}</div>
          <div class="text-xs text-muted mt-0.5">{{ __('pages.generator.stat_mult') }}</div>
        </div>
        <div class="rounded-lg border border-line bg-surface p-3 text-center">
          <div class="text-2xl font-bold text-brand">{{ $score->points }}</div>
          <div class="text-xs text-muted mt-0.5">{{ __('pages.generator.stat_points') }}</div>
        </div>
      </div>
      <p class="text-xs text-muted -mt-2">{{ __('pages.generator.score_hint') }}</p>

      {{-- Mapa --}}
      <div class="rounded-lg border border-line bg-surface p-3">
        <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.generator.map') }}</div>
        <div wire:ignore>
          <div id="edi-gen-mapa"></div>
        </div>
      </div>

      {{-- Živý EDI text --}}
      <div class="rounded-lg border border-line bg-surface p-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-semibold text-heading">{{ __('pages.generator.edi_text') }}</span>
          <button type="button" class="btn btn-sm" wire:click="download">⤓ {{ __('pages.generator.download') }}</button>
        </div>
        <pre class="edi-preview">{{ $ediText }}</pre>
      </div>

      {{-- Akce --}}
      <div class="rounded-lg border border-line bg-surface p-3">
        @if ($submittable)
          <button type="button" class="btn btn-primary w-full" wire:click="odeslat" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="odeslat">{{ __('pages.generator.submit') }}</span>
            <span wire:loading wire:target="odeslat">{{ __('pages.hlaseni.processing') }}</span>
          </button>
          <p class="text-xs text-muted mt-2">{{ __('pages.generator.submit_hint') }}</p>
        @else
          <p class="text-sm text-muted">{{ __('pages.generator.submit_only_144') }}</p>
        @endif
      </div>
    </div>
  </div>

  {{-- Datová schránka pro JS (mapa + persistence). Neobsahuje wire:ignore,
       takže se při každé změně překreslí čerstvými daty. --}}
  <div id="edi-gen-data"
       data-home="{{ json_encode($home) }}"
       data-points="{{ json_encode($mapPoints) }}"
       data-state="{{ json_encode([
          'tname' => $tname, 'pcall' => $pcall, 'pwwlo' => $pwwlo, 'psect' => $psect,
          'pband' => $pband, 'tdate' => $tdate, 'rname' => $rname, 'rphon' => $rphon,
          'rhbbs' => $rhbbs, 'spowe' => $spowe, 'stxeq' => $stxeq, 'sante' => $sante,
          'remarks' => $remarks, 'qsos' => $qsos,
       ]) }}"
       hidden></div>
</div>
