{{--
    Hlášení. Nové podání (EDI i ruční) řeší Livewire komponent <livewire:prihlaska>;
    editaci existujícího záznamu (admin / vlastník) ruční formulář níže. Pod tím
    průběžné výsledky.
--}}
@extends('layouts.app')

@section('title', __('pages.hlaseni.title'))

@section('content')
@php
    $e = $edit ?? null;
    $isAdmin = (bool) (auth()->user()?->is_admin);
    $val = fn (string $name, $editVal = null, $def = '') => old($name, $editVal ?? $def);
@endphp

{{-- announcement řeší centrální <x-flash /> v layoutu --}}

@if ($maAktivniKolo)

@if ($e === null)
{{-- ===== Nové podání – jednotný Livewire komponent (EDI náhled / ruční) ===== --}}
<livewire:prihlaska />
@else
{{-- ===== Editace existujícího záznamu (admin / vlastník) ===== --}}
@if ($errors->any())
    <x-alert type="error">
        @foreach ($errors->all() as $err)
            {{ $err }}<br>
        @endforeach
    </x-alert>
@endif

@if ($isAdmin && count($adminWarnings) > 0)
<div class="card mb-4 border-gray-300 bg-white">
    <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-3">
        <x-icon name="triangle-alert" class="h-5 w-5 flex-shrink-0 text-gray-500" />
        <p class="text-sm font-semibold text-gray-700">Varování administrátora</p>
    </div>
    <ul class="space-y-1 px-5 py-3 text-sm text-gray-800">
        @foreach ($adminWarnings as $w)
            <li class="flex items-start gap-2">
                <span class="mt-0.5 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold {{ $w->severity->badgeClasses() }}">{{ $w->severity->label() }}</span>
                <span>{{ $w->message }}</span>
            </li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('hlaseni.store') }}" method="post" class="card mb-6">
    <div class="flex items-center gap-3 border-b border-line px-5 py-4">
        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-soft">
            <x-icon name="file" class="h-5 w-5 text-brand" />
        </div>
        <p class="text-sm font-semibold text-heading">Editace záznamu</p>
    </div>
    <div class="p-5">
    @csrf
    <input type="hidden" name="id_zaznamu" value="{{ (int) ($e->id ?? 0) }}">
    <input type="hidden" name="edihead_id" value="{{ (int) $val('edihead_id', $e->edihead_id ?? 0, 0) }}">

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
        <x-field name="jmeno" :label="__('pages.hlaseni.field_name')" :value="$val('jmeno', $e->jmeno ?? '')" required />
        <x-field name="email" :label="__('pages.hlaseni.field_contact')" :value="$val('email', $e->mail ?? '')" :required="(int) $val('edihead_id', $e->edihead_id ?? 0, 0) > 0" />
        <x-field name="telefon" :label="__('pages.hlaseni.field_phone')" :value="$val('telefon', $e->telefon ?? '')" required />
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
                    @if ($isAdmin)<th>{{ __('pages.vysledky.col_actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
            @foreach ($radky as $i => $r)
                <tr @class(['row-pending' => ! $r->schvaleno])>
                    <td class="num font-bold">{{ $i + 1 }}.</td>
                    <td class="mono font-bold">
                        {{ $r->znacka }}@if ($r->qrp)<x-badge variant="qrp" class="ml-1">QRP</x-badge>@elseif ($r->lp)<x-badge variant="lp" class="ml-1">LP</x-badge>@endif
                        {{-- Vizualizace vlastního deníku – jen pro řádek aktuálního závodníka s nahraným EDI --}}
                        @if ($e && $r->id === $e->id && $r->edihead_id !== null)
                            <x-vizualizace-odkaz :head="$r->edihead_id" target="_blank" class="ml-1" />
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
                    @if ($isAdmin)
                        <td>@include('partials.zaznam-akce', ['r' => $r])</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@if ($isAdmin)
    @include('partials.del-modal')
@endif
@endif
@endsection
