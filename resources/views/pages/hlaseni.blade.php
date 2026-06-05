{{--
    Hlášení. Nahoře EDI upload box; ruční formulář se zobrazí jen když $showManual.
    Pod tím průběžné výsledky vybraného kola.
--}}
@extends('layouts.app')

@section('title', 'Hlášení / Log import – VKV PA')

@section('content')
@php
    $e = $edit ?? null;
    $val = fn (string $name, $editVal = null, $def = '') => old($name, $editVal ?? $def);
@endphp

@if (session('announcement'))
    <div class="alert alert-success">{{ session('announcement') }}</div>
@endif

@if ($maAktivniKolo)
{{-- ===== EDI upload box ===== --}}
<div class="card mb-6 p-5">
    <h1 class="!mb-3">Načíst EDI soubor / Import EDI file</h1>

    @if ($errors->has('upload'))
        <div class="alert alert-error">
            {{ $errors->first('upload') }}
            @foreach (session('lineErrors', []) as $le)
                <br><span class="font-normal">Chybný řádek: {{ $le }}</span>
            @endforeach
        </div>
    @endif

    <form action="{{ route('edi.store') }}" method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="upload" class="text-sm">
        <button type="submit" class="btn btn-primary">nahrát / upload</button>
    </form>

    <p class="mt-3 text-xs leading-relaxed text-muted">
        Lze použít jakýkoli logovací software, který umí edi export a nezáleží na tom, jestli samotný deník umí počítat body pro PA, nebo ne. Zcela vyhoví standardní konfigurace pro závody, ve kterých je jeden bod za kilometr, robot si spočítá body dle pravidel PA i vyhledá v deníku násobiče.<br><br>
        You can use any logging software that can export edi, and it doesn't matter whether the log itself can count points for OK Activity or not. You can use the standard VHF/UHF contest configuration, where one point per kilometer, robot calculates the points according to the OK Activity rules and looks up the multipliers in the log.
    </p>

    <div class="mt-3 border-t border-line pt-3">
        <a href="{{ route('hlaseni.index', ['showfrm' => 1]) }}" class="font-semibold">Nemám EDI soubor (vyplním hlášení ručně) / No EDI file</a>
    </div>
</div>

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

<form action="{{ route('hlaseni.store') }}" method="post" class="card p-5">
    @csrf
    <input type="hidden" name="id_zaznamu" value="{{ (int) ($e->id ?? 0) }}">
    <input type="hidden" name="EDIID" value="{{ (int) $val('EDIID', $e->EDI_ID ?? 0, 0) }}">

    <div class="grid gap-x-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="f-kolo">Kolo / Period *</label>
            <select id="f-kolo" name="kolo" class="select @error('kolo') input-err @enderror">
                <option value="">--- vyberte kolo / select period ---</option>
                @foreach ($kola as $k)
                    <option value="{{ $k->id }}" @selected((int) $val('kolo', $e->id_kola ?? 0) === $k->id)>{{ $k->nazev }}</option>
                @endforeach
            </select>
            @error('kolo')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="label" for="f-kat">Kategorie / Category *</label>
            <select id="f-kat" name="kategorie" class="select @error('kategorie') input-err @enderror">
                <option value="">--- vyberte kategorii / select ---</option>
                @foreach ($kategorie as $cat)
                    <option value="{{ $cat->id }}" @selected((int) $val('kategorie', $e->id_kategorie ?? 0) === $cat->id)>{{ $cat->nazev }}</option>
                @endforeach
            </select>
            @error('kategorie')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="label" for="f-znacka">Volací znak / Callsign *</label>
            <input id="f-znacka" name="znacka" type="text" class="input mono font-bold @error('znacka') input-err @enderror" value="{{ $val('znacka', $e->znacka ?? '') }}">
            @error('znacka')<span class="field-error">{{ $message }}</span>@enderror
        </div>

        <div class="field">
            <label class="label" for="f-loc">Lokátor / WWL *</label>
            <input id="f-loc" name="locator" type="text" class="input mono @error('locator') input-err @enderror" value="{{ $val('locator', $e->locator ?? '') }}">
            @error('locator')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <label class="mb-4 flex items-center gap-2 text-sm">
        <input type="checkbox" name="qrp" value="1" @checked($val('qrp', $e->qrp ?? false))>
        QRP (zaškrtněte, pokud jste v závodě použili výkon QRP)
    </label>

    {{-- Body / počty --}}
    <div class="grid grid-cols-2 gap-x-5 sm:grid-cols-4">
        <div class="field">
            <label class="label" for="f-pocet">Počet QSO *</label>
            <input id="f-pocet" name="pocet" type="text" class="input num @error('pocet') input-err @enderror" value="{{ (int) $val('pocet', $e->pocet ?? 0, 0) }}">
            @error('pocet')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-bzq">Bodů za QSO</label>
            <input id="f-bzq" name="bodu_za_qso" type="text" class="input num @error('bodu_za_qso') input-err @enderror" value="{{ (int) $val('bodu_za_qso', $e->bodu_za_qso ?? 0, 0) }}">
            @error('bodu_za_qso')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-nas">Násobiče *</label>
            <input id="f-nas" name="nasobice" type="text" class="input num @error('nasobice') input-err @enderror" value="{{ (int) $val('nasobice', $e->nasobice ?? 0, 0) }}">
            @error('nasobice')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-body">Celkem bodů *</label>
            <input id="f-body" name="body" type="text" class="input num font-bold @error('body') input-err @enderror" value="{{ (int) $val('body', $e->body ?? 0, 0) }}">
            @error('body')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="grid gap-x-5 sm:grid-cols-2">
        <div class="field">
            <label class="label" for="f-jmeno">Jméno / Name</label>
            <input id="f-jmeno" name="jmeno" type="text" class="input" value="{{ $val('jmeno', $e->jmeno ?? '') }}">
        </div>
        <div class="field">
            <label class="label" for="f-email">Kontakt / Contact *</label>
            <input id="f-email" name="email" type="text" class="input @error('email') input-err @enderror" value="{{ $val('email', $e->mail ?? '') }}">
            @error('email')<span class="field-error">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label class="label" for="f-tel">Telefon</label>
            <input id="f-tel" name="telefon" type="text" class="input @error('telefon') input-err @enderror" value="{{ $val('telefon', $e->telefon ?? '') }}">
            @error('telefon')<span class="field-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="field">
        <label class="label" for="f-pozn">Poznámka / Note</label>
        <textarea id="f-pozn" name="poznamka" class="textarea" rows="2">{{ $val('poznamka', $e->poznamka ?? '') }}</textarea>
    </div>

    <div class="field">
        <label class="label" for="f-soap">Soapbox</label>
        <textarea id="f-soap" name="soapbox" class="textarea" rows="4">{{ $val('soapbox', $e->soapbox ?? '') }}</textarea>
    </div>

    <div class="mt-2 flex items-center justify-between">
        <a href="{{ route('hlaseni.index') }}" class="text-sm">vymazat formulář</a>
        <button type="submit" name="Odeslat" value="Odeslat / Send" class="btn btn-primary">Odeslat / Send</button>
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
    <div class="section-head">Průběžné výsledky kola — {{ $katMap[$katId]->nazev ?? ('kategorie ' . $katId) }}</div>
    <div class="table-wrap mb-4">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">Poř.</th>
                    <th>Značka</th>
                    <th>Lokátor</th>
                    <th class="num">QSO</th>
                    <th class="num">Násobiče</th>
                    <th class="num">Celkem bodů</th>
                    <th>Jméno / Poznámka</th>
                    <th>Stav</th>
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
                            <span class="badge badge-ok">OK</span>
                        @else
                            <span class="badge badge-warn">Čeká</span>
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
