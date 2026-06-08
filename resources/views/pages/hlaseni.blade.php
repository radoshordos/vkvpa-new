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

@if (session('announcement'))
    <div class="alert alert-success">{{ session('announcement') }}</div>
@endif

@if (!empty(session('importWarnings')))
    <div class="alert alert-warning">
        <strong>{{ __('pages.hlaseni.import_warnings') }}</strong>
        <ul class="mt-1 list-disc pl-5">
            @foreach (session('importWarnings') as $w)
                <li class="font-normal">{{ $w }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($maAktivniKolo)

{{-- ===== Tab navigace ===== --}}
<div class="tab-nav">
    <a href="{{ route('hlaseni.index') }}" class="tab-btn {{ !$showManual ? 'active' : '' }}">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M9 2H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6L9 2Z"/>
            <path d="M9 2v4h4M8 9.5v3M6.5 11l1.5-1.5 1.5 1.5"/>
        </svg>
        {{ __('pages.hlaseni.tab_edi') }}
    </a>
    <a href="{{ route('hlaseni.index', ['showfrm' => 1]) }}" class="tab-btn {{ $showManual ? 'active' : '' }}">
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M11.5 2.5a1.5 1.5 0 0 1 2 2L5 13l-3 1 1-3 8.5-8.5Z"/>
        </svg>
        {{ __('pages.hlaseni.tab_manual') }}
    </a>
</div>

{{-- ===== EDI upload panel ===== --}}
@if (!$showManual)
<div class="card mb-6 p-5">
    @if ($errors->has('upload'))
        <div class="alert alert-error">
            {{ $errors->first('upload') }}
            @foreach (session('lineErrors', []) as $le)
                <br><span class="font-normal">{{ __('pages.hlaseni.error_line') }}: {{ $le }}</span>
            @endforeach
        </div>
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
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4"/>
            </svg>
        </a>
    </div>
</div>
@endif

{{-- ===== Ruční formulář – jen když je potřeba ===== --}}
@if ($showManual)
@if ($errors->any() && ! ($errors->count() === 1 && $errors->has('upload')))
    <div class="alert alert-error">
        @foreach ($errors->all() as $err)
            @if ($err !== $errors->first('upload'))
                {{ $err }}<br>
            @endif
        @endforeach
    </div>
@endif

<form action="{{ route('hlaseni.store') }}" method="post" class="card p-5 mb-6">
    @csrf
    <input type="hidden" name="id_zaznamu" value="{{ (int) ($e->id ?? 0) }}">
    <input type="hidden" name="EDIID" value="{{ (int) $val('EDIID', $e->EDI_ID ?? 0, 0) }}">

    <div class="grid gap-x-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="f-kolo">{{ __('pages.hlaseni.field_period') }} *</label>
            <select id="f-kolo" name="kolo" class="select @error('kolo') input-err @enderror">
                <option value="">{{ __('pages.hlaseni.select_period') }}</option>
                @foreach ($kola as $k)
                    <option value="{{ $k->id }}" @selected((int) $val('kolo', $e->id_kola ?? 0) === $k->id)>{{ $k->nazev }}</option>
                @endforeach
            </select>
            @error('kolo')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="label" for="f-kat">{{ __('pages.hlaseni.field_category') }} *</label>
            <select id="f-kat" name="kategorie" class="select @error('kategorie') input-err @enderror">
                <option value="">{{ __('pages.hlaseni.select_category') }}</option>
                @foreach ($kategorie as $cat)
                    <option value="{{ $cat->id }}" @selected((int) $val('kategorie', $e->id_kategorie ?? 0) === $cat->id)>{{ $cat->nazev }}</option>
                @endforeach
            </select>
            @error('kategorie')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="label" for="f-znacka">{{ __('pages.hlaseni.field_callsign') }} *</label>
            <input id="f-znacka" name="znacka" type="text" class="input mono font-bold @error('znacka') input-err @enderror" value="{{ $val('znacka', $e->znacka ?? '') }}">
            @error('znacka')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="label" for="f-loc">{{ __('pages.hlaseni.field_locator') }} *</label>
            <input id="f-loc" name="locator" type="text" class="input mono @error('locator') input-err @enderror" value="{{ $val('locator', $e->locator ?? '') }}">
            @error('locator')<span class="field-error">{{ $message }}</span>@enderror
        </div>
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
        <div class="field">
            <label class="label" for="f-pocet">{{ __('pages.hlaseni.field_qso') }} *</label>
            <input id="f-pocet" name="pocet" type="text" class="input num @error('pocet') input-err @enderror" value="{{ (int) $val('pocet', $e->pocet ?? 0, 0) }}">
            @error('pocet')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-bzq">{{ __('pages.hlaseni.field_qso_pts') }}</label>
            <input id="f-bzq" name="bodu_za_qso" type="text" class="input num @error('bodu_za_qso') input-err @enderror" value="{{ (int) $val('bodu_za_qso', $e->bodu_za_qso ?? 0, 0) }}">
            @error('bodu_za_qso')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-nas">{{ __('pages.hlaseni.field_mult') }} *</label>
            <input id="f-nas" name="nasobice" type="text" class="input num @error('nasobice') input-err @enderror" value="{{ (int) $val('nasobice', $e->nasobice ?? 0, 0) }}">
            @error('nasobice')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-body">{{ __('pages.hlaseni.field_total') }} *</label>
            <input id="f-body" name="body" type="text" class="input num font-bold @error('body') input-err @enderror" value="{{ (int) $val('body', $e->body ?? 0, 0) }}">
            @error('body')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="grid gap-x-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="f-jmeno">{{ __('pages.hlaseni.field_name') }}</label>
            <input id="f-jmeno" name="jmeno" type="text" class="input" value="{{ $val('jmeno', $e->jmeno ?? '') }}">
        </div>
        <div class="field">
            <label class="label" for="f-email">{{ __('pages.hlaseni.field_contact') }} *</label>
            <input id="f-email" name="email" type="text" class="input @error('email') input-err @enderror" value="{{ $val('email', $e->mail ?? '') }}">
            @error('email')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-tel">{{ __('pages.hlaseni.field_phone') }}</label>
            <input id="f-tel" name="telefon" type="text" class="input @error('telefon') input-err @enderror" value="{{ $val('telefon', $e->telefon ?? '') }}">
            @error('telefon')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="field">
        <label class="label" for="f-pozn">{{ __('pages.hlaseni.field_note') }}</label>
        <textarea id="f-pozn" name="poznamka" class="textarea" rows="2">{{ $val('poznamka', $e->poznamka ?? '') }}</textarea>
    </div>

    <div class="field">
        <label class="label" for="f-soap">{{ __('pages.hlaseni.field_soapbox') }}</label>
        <textarea id="f-soap" name="soapbox" class="textarea" rows="4">{{ $val('soapbox', $e->soapbox ?? '') }}</textarea>
    </div>

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
