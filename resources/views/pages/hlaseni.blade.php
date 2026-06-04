{{--
    Hlášení. Nahoře EDI upload box; ruční formulář se zobrazí jen když $showManual.
    Pod tím průběžné výsledky vybraného kola.
--}}
@extends('layouts.app')

@section('title', 'Hlášení / Log import – VKV PA')

@push('head')
<style>
    .vkv-table { width: 100%; border-collapse: collapse; background-color: #D6ECF3; font-family: Arial, sans-serif; font-size: 13px; color: black; }
    .vkv-table td { padding: 6px 10px; border-bottom: 2px solid white; vertical-align: middle; }
    .vkv-table tr:last-child td { border-bottom: none; }
    .vkv-input { border: 1px solid black; padding: 2px; font-size: 13px; background: white; }
    .vkv-input-bold { font-weight: bold; }
    .vkv-select { border: 1px solid black; padding: 1px; background: white; font-size: 13px; }
    .vkv-text-area { border: 1px solid black; width: 100%; margin-top: 5px; background: white; font-family: Arial, sans-serif; }
    .vkv-error { background: #fff3f3; border: 1px solid #cc0000; color: #cc0000; padding: 10px; margin: 10px 0; font-family: Arial; font-size: 13px; font-weight: bold; }
    .vkv-field-error { color: #cc0000; font-size: 11px; display: block; margin-top: 2px; }
    .vkv-input-err { border: 1px solid #cc0000 !important; background: #fff8f8 !important; }
    .vkv-select-err { border: 1px solid #cc0000 !important; background: #fff8f8 !important; }
    .vkv-edi-box { background: #eee; padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; }
</style>
@endpush

@section('content')
@php
    $e = $edit ?? null;
    $val = fn (string $name, $editVal = null, $def = '') => old($name, $editVal ?? $def);
@endphp

@if (session('announcement'))
    <div style="background:#f0fff0;border:1px solid #2a2;color:#161;padding:10px;margin:10px 0;font-family:Arial;font-size:13px;">
        {{ session('announcement') }}
    </div>
@endif

@if ($maAktivniKolo)
{{-- ===== EDI upload box (tpl_form_edi.php) ===== --}}
<div class="vkv-edi-box">
    <h1 style="color: #000080; font-size: 20px; margin-top: 0;">Načíst EDI soubor / Import EDI file</h1>

    @if ($errors->has('upload'))
        <div class="vkv-error">
            {{ $errors->first('upload') }}
            @foreach (session('lineErrors', []) as $le)
                <br><span style="font-weight:normal;">Chybný řádek: {{ $le }}</span>
            @endforeach
        </div>
    @endif

    <form action="{{ route('edi.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        EDI soubor: <input type="file" name="upload" size="30" style="border: 1px solid #777; background: white;">
        <input type="submit" value="nahrát / upload" style="font-weight: bold; cursor: pointer;">
        <p style="font-size: 11px; color: #333; line-height: 1.4; margin-top: 10px;">
            Lze použít jakýkoli logovací software, který umí edi export a nezáleží na tom, jestli samotný deník umí počítat body pro PA, nebo ne. Zcela vyhoví standardní konfigurace pro závody, ve kterých je jeden bod za kilometr, robot si spočítá body dle pravidel PA i vyhledá v deníku násobiče.<br><br>
            You can use any logging software that can export edi, and it doesn't matter whether the log itself can count points for OK Activity or not. You can use the standard VHF/UHF contest configuration, where one point per kilometer, robot calculates the points according to the OK Activity rules and looks up the multipliers in the log.
        </p>
    </form>

    <div style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 5px;">
        <b style="color: #A52A2A;">
            <a href="{{ route('hlaseni.index', ['showfrm' => 1]) }}" style="color: #A52A2A; text-decoration: underline;">Nemám EDI soubor (vyplním hlášení ručně) / No EDI file</a>
        </b>
    </div>
</div>

{{-- ===== Ruční formulář (tpl_form_manual.php) – jen když je potřeba ===== --}}
@if ($showManual)
<hr>

@if ($errors->any() && ! ($errors->count() === 1 && $errors->has('upload')))
    <div class="vkv-error">
        @foreach ($errors->all() as $err)
            @if ($err !== $errors->first('upload'))
                {{ $err }}<br>
            @endif
        @endforeach
    </div>
@endif

<form action="{{ route('hlaseni.store') }}" method="post">
    @csrf
    <input type="hidden" name="id_zaznamu" value="{{ (int) ($e->id ?? 0) }}">
    <input type="hidden" name="EDIID" value="{{ (int) $val('EDIID', $e->EDI_ID ?? 0, 0) }}">

    <table class="vkv-table">
        <tr>
            <td width="150">Kolo *<br>Period *</td>
            <td>
                <select name="kolo" class="vkv-select @error('kolo') vkv-select-err @enderror" style="width: 250px;">
                    <option value="">--- vyberte kolo / select period ---</option>
                    @foreach ($kola as $k)
                        <option value="{{ $k->id }}" @selected((int) $val('kolo', $e->id_kola ?? 0) === $k->id)>{{ $k->nazev }}</option>
                    @endforeach
                </select>
                @error('kolo')<span class="vkv-field-error">{{ $message }}</span>@enderror
            </td>
            <td colspan="2"></td>
        </tr>

        <tr>
            <td>Kategorie *<br>Category *</td>
            <td>
                <select name="kategorie" class="vkv-select @error('kategorie') vkv-select-err @enderror" style="width: 250px;">
                    <option value="">--- vyberte kategorii / select ---</option>
                    @foreach ($kategorie as $cat)
                        <option value="{{ $cat->id }}" @selected((int) $val('kategorie', $e->id_kategorie ?? 0) === $cat->id)>{{ $cat->nazev }}</option>
                    @endforeach
                </select>
                @error('kategorie')<span class="vkv-field-error">{{ $message }}</span>@enderror
            </td>
            <td colspan="2">
                <input type="checkbox" name="qrp" value="1" @checked($val('qrp', $e->qrp ?? false))>
                QRP (zaškrtněte, pokud jste v závodě použili výkon QRP)
            </td>
        </tr>

        <tr>
            <td><strong>Volací znak *<br>Callsign *</strong></td>
            <td>
                <input name="znacka" type="text" class="vkv-input vkv-input-bold @error('znacka') vkv-input-err @enderror" value="{{ $val('znacka', $e->znacka ?? '') }}" size="25">
                @error('znacka')<span class="vkv-field-error">{{ $message }}</span>@enderror
            </td>
            <td width="100">Lokátor *<br>WWL *</td>
            <td>
                <input name="locator" type="text" class="vkv-input @error('locator') vkv-input-err @enderror" value="{{ $val('locator', $e->locator ?? '') }}" size="15">
                @error('locator')<span class="vkv-field-error">{{ $message }}</span>@enderror
            </td>
        </tr>

        <tr>
            <td colspan="4">
                <table width="100%" cellpadding="0" cellspacing="0" style="border:none;">
                    <tr>
                        <td style="border:none;">Počet QSO *</td>
                        <td style="border:none;">
                            <input name="pocet" type="text" class="vkv-input @error('pocet') vkv-input-err @enderror" value="{{ (int) $val('pocet', $e->pocet ?? 0, 0) }}" size="6">
                            @error('pocet')<span class="vkv-field-error">{{ $message }}</span>@enderror
                        </td>
                        <td style="border:none;">Bodů za QSO</td>
                        <td style="border:none;">
                            <input name="bodu_za_qso" type="text" class="vkv-input @error('bodu_za_qso') vkv-input-err @enderror" value="{{ (int) $val('bodu_za_qso', $e->bodu_za_qso ?? 0, 0) }}" size="6">
                            @error('bodu_za_qso')<span class="vkv-field-error">{{ $message }}</span>@enderror
                        </td>
                        <td style="border:none;">Násobiče *</td>
                        <td style="border:none;">
                            <input name="nasobice" type="text" class="vkv-input @error('nasobice') vkv-input-err @enderror" value="{{ (int) $val('nasobice', $e->nasobice ?? 0, 0) }}" size="6">
                            @error('nasobice')<span class="vkv-field-error">{{ $message }}</span>@enderror
                        </td>
                        <td style="border:none;">Celkem bodů *</td>
                        <td style="border:none;">
                            <input name="body" type="text" class="vkv-input vkv-input-bold @error('body') vkv-input-err @enderror" value="{{ (int) $val('body', $e->body ?? 0, 0) }}" size="10" style="background-color: #ffffcc;">
                            @error('body')<span class="vkv-field-error">{{ $message }}</span>@enderror
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td>Jméno / Name</td>
            <td colspan="3"><input name="jmeno" type="text" class="vkv-input" value="{{ $val('jmeno', $e->jmeno ?? '') }}" style="width: 300px;"></td>
        </tr>
        <tr>
            <td>Kontakt / Contact:*</td>
            <td>
                <input name="email" type="text" class="vkv-input @error('email') vkv-input-err @enderror" value="{{ $val('email', $e->mail ?? '') }}" style="width: 280px;">
                @error('email')<span class="vkv-field-error">{{ $message }}</span>@enderror
            </td>
            <td align="right">telefon</td>
            <td>
                <input name="telefon" type="text" class="vkv-input @error('telefon') vkv-input-err @enderror" value="{{ $val('telefon', $e->telefon ?? '') }}" style="width: 200px;">
                @error('telefon')<span class="vkv-field-error">{{ $message }}</span>@enderror
            </td>
        </tr>

        <tr>
            <td colspan="4">
                <strong>Poznámka / Note</strong><br>
                <textarea name="poznamka" class="vkv-text-area" style="height: 40px;">{{ $val('poznamka', $e->poznamka ?? '') }}</textarea>
            </td>
        </tr>

        <tr>
            <td colspan="4">
                <strong>Soapbox:</strong><br>
                <textarea name="soapbox" class="vkv-text-area" style="height: 80px;">{{ $val('soapbox', $e->soapbox ?? '') }}</textarea>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <a href="{{ route('hlaseni.index') }}" style="color: #CC0000; text-decoration: underline;">vymazat formulář</a>
            </td>
            <td colspan="2" align="right">
                <input type="submit" name="Odeslat" value="Odeslat / Send" style="padding: 5px 20px; font-weight: bold; cursor: pointer;">
            </td>
        </tr>
    </table>
</form>
@endif

@else
    @include('partials.no-active-period')
@endif

{{-- ===== Průběžné výsledky vybraného kola (styl vysledky.php) ===== --}}
@if ($vysledky->isNotEmpty())
@php $katMap = $kategorie->keyBy('id'); @endphp
@foreach ($vysledky->groupBy('id_kategorie') as $katId => $radky)
    <h1 style="font-size: 1.2em; margin-top: 15px; margin-bottom: 5px; color: #22108b; border-bottom: 3px solid #bababa; text-align: left;">
        Průběžné výsledky kola — {{ $katMap[$katId]->nazev ?? ('kategorie ' . $katId) }}
    </h1>
    <table width="100%" cellpadding="4" cellspacing="1" bgcolor="#b4b4b4" style="font-size: 0.9em; border-collapse: separate; margin-top: 5px;">
        <tr bgcolor="#e6e6fa" style="color: #000080; font-weight: bold;">
            <th align="center" width="40">Poř.</th>
            <th align="left" width="100">Značka</th>
            <th align="center" width="80">Lokátor</th>
            <th align="right" width="70">QSO</th>
            <th align="right" width="80">Násobiče</th>
            <th align="right" width="80">Celkem bodů</th>
            <th align="left">Jméno / Poznámka</th>
            <th align="center" width="60">Stav</th>
        </tr>
        @foreach ($radky as $i => $r)
            @php $bg = ! $r->schvaleno ? '#ffdab9' : (($i % 2) ? '#d6ecf3' : '#ffffff'); @endphp
            <tr bgcolor="{{ $bg }}" style="color: black;">
                <td align="center"><b>{{ $i + 1 }}.</b></td>
                <td align="left"><b>{{ $r->znacka }}</b>{{ $r->qrp ? ' /QRP' : '' }}</td>
                <td align="center">{{ $r->locator }}</td>
                <td align="right">{{ (int) $r->pocet }}</td>
                <td align="right">{{ (int) $r->nasobice }}</td>
                <td align="right"><b>{{ (int) $r->body }}</b></td>
                <td align="left" class="small">{{ $r->jmeno }} @if ($r->poznamka)<i>({{ $r->poznamka }})</i>@endif</td>
                <td align="center">
                    @if ($r->schvaleno)
                        <font color="#2db62f"><b>OK</b></font>
                    @else
                        <font color="#ff6347">Čeká</font>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endforeach
@endif
@endsection
