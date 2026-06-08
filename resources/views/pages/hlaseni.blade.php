{{--
    Hlášení. Nahoře tab navigace; EDI panel nebo ruční formulář dle aktivní záložky.
    Pod tím průběžné výsledky vybraného kola.
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

{{-- ===== Tab navigace ===== --}}
<div class="tab-nav">
    <a href="{{ route('hlaseni.index') }}" class="tab-btn {{ !$showManual ? 'active' : '' }}">
        <x-icon name="file" />
        {{ __('pages.hlaseni.tab_edi') }}
    </a>
    <a href="{{ route('hlaseni.index', ['showfrm' => 1]) }}" class="tab-btn {{ $showManual ? 'active' : '' }}">
        <x-icon name="pencil" />
        {{ __('pages.hlaseni.tab_manual') }}
    </a>
</div>

{{-- ===== EDI upload panel ===== --}}
@if (!$showManual)
<div class="card mb-6 p-5">
    @if ($errors->has('upload'))
        <x-alert type="error">
            {{ $errors->first('upload') }}
            @foreach (session('lineErrors', []) as $le)
                <br><span class="font-normal">{{ __('pages.hlaseni.error_line') }}: {{ $le }}</span>
            @endforeach
        </x-alert>
    @endif

    <form action="{{ route('edi.store') }}" method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="upload" class="text-sm">
        <button type="submit" class="btn btn-primary">{{ __('pages.hlaseni.btn_upload') }}</button>
    </form>

    <p class="mt-3 text-xs leading-relaxed text-muted">
        {{ __('pages.hlaseni.edi_info') }}
    </p>

    <div class="mt-3 border-t border-line pt-3">
        <a href="{{ route('hlaseni.index', ['showfrm' => 1]) }}" class="link-arrow">
            {{ __('pages.hlaseni.no_edi_link') }}
            <x-icon name="arrow-right" />
        </a>
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
                    <td class="mono font-bold">{{ $r->znacka }}{{ $r->qrp ? ' /QRP' : '' }}</td>
                    <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                    <td class="num">{{ (int) $r->pocet }}</td>
                    <td class="num">{{ (int) $r->nasobice }}</td>
                    <td class="num font-bold">{{ (int) $r->body }}</td>
                    <td class="text-muted">{{ $r->jmeno }} @if ($r->poznamka)<i>({{ $r->poznamka }})</i>@endif</td>
                    <td>
                        @if ($r->schvaleno)
                            <span class="badge badge-ok">{{ __('pages.hlaseni.status_ok') }}</span>
                        @else
                            <span class="badge badge-warn">{{ __('pages.hlaseni.status_pending') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
@endif
@endsection
