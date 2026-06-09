{{--
    Hlášení. EDI upload nebo ruční formulář dle stavu; pod tím průběžné výsledky.
--}}
@extends('layouts.app')

@section('title', __('pages.hlaseni.title'))

@section('content')
@php
    $e = $edit ?? null;
    $val = fn (string $name, $editVal = null, $def = '') => old($name, $editVal ?? $def);
@endphp

{{-- announcement řeší centrální <x-flash /> v layoutu --}}

@if (!empty(session('importWarnings')))
    <x-alert type="warning">
        <strong>{{ __('pages.hlaseni.import_warnings') }}</strong>
        <ul class="mt-1 list-disc pl-5">
            @foreach (session('importWarnings') as $w)
                <li class="font-normal">{{ $w }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

@if ($maAktivniKolo)

{{-- ===== EDI upload panel ===== --}}
@if (!$showManual)
<div class="card mb-6">
    <div class="flex items-center gap-3 border-b border-line px-5 py-4">
        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-soft">
            <x-icon name="file" class="h-5 w-5 text-brand" />
        </div>
        <p class="text-sm font-semibold text-heading">{{ __('pages.hlaseni.heading_edi') }}</p>
    </div>

    <div class="px-5 py-4">
        @if ($errors->has('upload'))
            <x-alert type="error" class="mb-3">
                {{ $errors->first('upload') }}
                @foreach (session('lineErrors', []) as $le)
                    <br><span class="font-normal">{{ __('pages.hlaseni.error_line') }}: {{ $le }}</span>
                @endforeach
            </x-alert>
        @endif

        <form action="{{ route('edi.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <label class="upload-zone" id="edi-zone">
                <input
                    type="file" name="upload" id="edi-file" accept=".edi,.txt" class="sr-only"
                    onchange="var z=document.getElementById('edi-zone'),n=document.getElementById('edi-name');z.classList.toggle('has-file',!!this.files[0]);n.textContent=this.files[0]?this.files[0].name:''"
                >
                <svg class="upload-zone-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                </svg>
                <span id="edi-name" class="upload-zone-name">{{ __('pages.hlaseni.tab_edi') }}…</span>
                <span class="upload-zone-hint">{{ __('pages.hlaseni.edi_info') }}</span>
            </label>

            <div class="mt-3 flex items-center gap-3">
                <button type="submit" class="btn btn-primary">{{ __('pages.hlaseni.btn_upload') }}</button>
            </div>
        </form>

        <div class="mt-4 border-t border-line pt-4">
            <a href="{{ route('hlaseni.index', ['showfrm' => 1]) }}" class="link-arrow">
                {{ __('pages.hlaseni.no_edi_link') }}
                <x-icon name="arrow-right" />
            </a>
        </div>
    </div>
</div>
@endif

{{-- ===== Ruční formulář – jen když je potřeba ===== --}}
@if ($showManual)
@if ($errors->any() && ! ($errors->count() === 1 && $errors->has('upload')))
    <x-alert type="error">
        @foreach ($errors->all() as $err)
            @if ($err !== $errors->first('upload'))
                {{ $err }}<br>
            @endif
        @endforeach
    </x-alert>
@endif

<form action="{{ route('hlaseni.store') }}" method="post" class="card p-5 mb-6">
    @csrf
    <input type="hidden" name="id_zaznamu" value="{{ (int) ($e->id ?? 0) }}">
    <input type="hidden" name="EDIID" value="{{ (int) $val('EDIID', $e->EDI_ID ?? 0, 0) }}">

    <div class="grid gap-x-5 sm:grid-cols-2">
        <x-field name="kolo" :label="__('pages.hlaseni.field_period')" required>
            <x-slot:control>
                <select id="f-kolo" name="kolo" @class(['select', 'input-err' => $errors->has('kolo')])>
                    <option value="">{{ __('pages.hlaseni.select_period') }}</option>
                    @foreach ($kola as $k)
                        <option value="{{ $k->id }}" @selected((int) $val('kolo', $e->id_kola ?? 0) === $k->id)>{{ $k->nazev }}</option>
                    @endforeach
                </select>
            </x-slot:control>
        </x-field>

        <x-field name="kategorie" :label="__('pages.hlaseni.field_category')" required>
            <x-slot:control>
                <select id="f-kategorie" name="kategorie" @class(['select', 'input-err' => $errors->has('kategorie')])>
                    <option value="">{{ __('pages.hlaseni.select_category') }}</option>
                    @foreach ($kategorie as $cat)
                        <option value="{{ $cat->id }}" @selected((int) $val('kategorie', $e->id_kategorie ?? 0) === $cat->id)>{{ $cat->nazev }}</option>
                    @endforeach
                </select>
            </x-slot:control>
        </x-field>

        <x-field name="znacka" :label="__('pages.hlaseni.field_callsign')" :value="$val('znacka', $e->znacka ?? '')" required class="mono font-bold" />

        <x-field name="locator" :label="__('pages.hlaseni.field_locator')" :value="$val('locator', $e->locator ?? '')" required class="mono" />
    </div>

    <label class="mb-2 flex items-center gap-2 text-sm">
        <input type="checkbox" name="qrp" value="1" @checked($val('qrp', $e->qrp ?? false))>
        {{ __('pages.hlaseni.field_qrp') }}
    </label>

    <label class="mb-4 flex items-center gap-2 text-sm">
        <input type="checkbox" name="lp" value="1" @checked($val('lp', $e->lp ?? false))>
        {{ __('pages.hlaseni.field_lp') }}
    </label>

    {{-- Body / počty --}}
    <div class="grid grid-cols-2 gap-x-5 sm:grid-cols-4">
        <x-field name="pocet" :label="__('pages.hlaseni.field_qso')" :value="(int) $val('pocet', $e->pocet ?? 0, 0)" required class="num" />
        <x-field name="bodu_za_qso" :label="__('pages.hlaseni.field_qso_pts')" :value="(int) $val('bodu_za_qso', $e->bodu_za_qso ?? 0, 0)" class="num" />
        <x-field name="nasobice" :label="__('pages.hlaseni.field_mult')" :value="(int) $val('nasobice', $e->nasobice ?? 0, 0)" required class="num" />
        <x-field name="body" :label="__('pages.hlaseni.field_total')" :value="(int) $val('body', $e->body ?? 0, 0)" required class="num font-bold" />
    </div>

    <div class="grid gap-x-5 sm:grid-cols-2">
        <x-field name="jmeno" :label="__('pages.hlaseni.field_name')" :value="$val('jmeno', $e->jmeno ?? '')" />
        <x-field name="email" :label="__('pages.hlaseni.field_contact')" :value="$val('email', $e->mail ?? '')" required />
        <x-field name="telefon" :label="__('pages.hlaseni.field_phone')" :value="$val('telefon', $e->telefon ?? '')" />
    </div>

    <x-field name="poznamka" :label="__('pages.hlaseni.field_note')">
        <x-slot:control>
            <textarea id="f-poznamka" name="poznamka" class="textarea" rows="2">{{ $val('poznamka', $e->poznamka ?? '') }}</textarea>
        </x-slot:control>
    </x-field>

    <x-field name="soapbox" :label="__('pages.hlaseni.field_soapbox')">
        <x-slot:control>
            <textarea id="f-soapbox" name="soapbox" class="textarea" rows="4">{{ $val('soapbox', $e->soapbox ?? '') }}</textarea>
        </x-slot:control>
    </x-field>

    <div class="mt-2 flex items-center justify-between">
        <a href="{{ route('hlaseni.index') }}" class="text-sm">{{ __('pages.hlaseni.btn_clear') }}</a>
        <button type="submit" name="Odeslat" value="Odeslat" class="btn btn-primary">{{ __('pages.hlaseni.btn_send') }}</button>
    </div>
</form>
@endif

@else
    @include('partials.no-active-period')
@endif

{{-- ===== Průběžné výsledky vybraného kola ===== --}}
@if ($vysledky->isNotEmpty())
@php $katMap = $kategorie->keyBy('id'); @endphp
@foreach ($vysledky->groupBy('id_kategorie') as $katId => $radky)
    <div class="section-head">{{ __('pages.hlaseni.interim_results') }} — {{ $katMap[$katId]->nazev ?? ('kategorie ' . $katId) }}</div>
    <div class="table-wrap mb-4">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">{{ __('pages.hlaseni.col_pos') }}</th>
                    <th>{{ __('pages.hlaseni.col_callsign') }}</th>
                    <th>{{ __('pages.hlaseni.col_locator') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_qso') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_mult') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_total') }}</th>
                    <th>{{ __('pages.hlaseni.col_name_note') }}</th>
                    <th>{{ __('pages.hlaseni.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($radky as $i => $r)
                <tr @class(['row-pending' => ! $r->schvaleno])>
                    <td class="num font-bold">{{ $i + 1 }}.</td>
                    <td class="mono font-bold">
                        {{ $r->znacka }}{{ $r->qrp ? ' /QRP' : '' }}
                        {{-- Vizualizace vlastního deníku – jen pro řádek aktuálního závodníka s nahraným EDI --}}
                        @if ($e && $r->id === $e->id && (int) $r->EDI_ID > 0)
                            <x-vizualizace-odkaz :head="(int) $r->EDI_ID" target="_blank" class="ml-1" />
                        @endif
                    </td>
                    <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                    <td class="num">{{ (int) $r->pocet }}</td>
                    <td class="num">{{ (int) $r->nasobice }}</td>
                    <td class="num font-bold">{{ (int) $r->body }}</td>
                    <td class="text-muted">{{ $r->jmeno }} @if ($r->poznamka)<i>({{ $r->poznamka }})</i>@endif</td>
                    <td>
                        @if ($r->schvaleno)
                            <x-badge variant="ok">{{ __('pages.hlaseni.status_ok') }}</x-badge>
                        @else
                            <x-badge variant="warn">{{ __('pages.hlaseni.status_pending') }}</x-badge>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
@endif

@push('scripts')
<script>
(function () {
    var zone  = document.getElementById('edi-zone');
    var input = document.getElementById('edi-file');
    if (! zone || ! input) return;

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', function (e) {
        if (! zone.contains(e.relatedTarget)) zone.classList.remove('dragover');
    });

    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            // change spustí stejnou obsluhu jako ruční výběr (zobrazení názvu)
            input.dispatchEvent(new Event('change'));
        }
    });

    // Drop mimo zónu nesmí otevřít soubor místo stránky
    document.addEventListener('dragover', function (e) { e.preventDefault(); });
    document.addEventListener('drop', function (e) { e.preventDefault(); });
}());
</script>
@endpush
@endsection
