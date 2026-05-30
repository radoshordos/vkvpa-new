{{--
    Ruční formulář hlášení (Fáze 6c) – zrcadlení legacy tpl_form_manual.php.
    Zachován vzhled (vkv-table) i dvojjazyčné popisky; napojeno na Eloquent,
    route() a StoreHlaseniRequest. Názvy polí sjednoceny s backendem
    (locator → lokator, email → mail).
--}}
@extends('layouts.app')

@section('title', 'Odeslat deník / Log import – VKV PA')

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
</style>
@endpush

@section('content')
@php
    $p = session('edi_prefill', []);
    $e = $edit ?? null;
    // Hodnota pole: old() → EDI prefill → editovaný záznam → default.
    $val = fn (string $name, $editVal = null, $def = '') => old($name, $p[$name] ?? ($editVal ?? $def));
@endphp

<h1 style="margin-top: 40px;">Odeslat deník / Log import</h1>

@if (session('announcement'))
    <div style="background:#f0fff0;border:1px solid #2a2;color:#161;padding:10px;margin:10px 0;font-family:Arial;font-size:13px;">
        {{ session('announcement') }}
    </div>
@endif

@if ($errors->any())
    <div class="vkv-error">
        @foreach ($errors->all() as $err)
            {{ $err }}<br>
        @endforeach
    </div>
@endif

<form action="{{ $e ? route('hlaseni.update', $e->id) : route('hlaseni.store') }}" method="post">
    @csrf
    @if ($e) @method('PUT') @endif
    <input type="hidden" name="EDI" value="{{ $val('EDI', $e->EDI ?? 0, 0) }}">
    <input type="hidden" name="EDIID" value="{{ $val('EDIID', $e->EDI_ID ?? 0, 0) }}">

    <table class="vkv-table">
        <tr>
            <td width="150">Kolo *<br>Period *</td>
            <td>
                <select name="kolo" class="vkv-select" style="width: 250px;">
                    <option value="">--- vyberte kolo / select period ---</option>
                    @foreach ($kola as $k)
                        <option value="{{ $k->id }}" @selected((int) $val('kolo', $e->id_kola ?? ($kolo->id ?? 0)) === $k->id)>
                            {{ $k->nazev }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td colspan="2"></td>
        </tr>

        <tr>
            <td>Kategorie *<br>Category *</td>
            <td>
                <select name="kategorie" class="vkv-select" style="width: 250px;">
                    <option value="">--- vyberte kategorii / select ---</option>
                    @foreach ($kategorie as $cat)
                        <option value="{{ $cat->id }}" @selected((int) $val('kategorie', $e->id_kategorie ?? 0) === $cat->id)>
                            {{ $cat->nazev }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td colspan="2">
                <input type="checkbox" name="qrp" value="1" @checked($val('qrp', $e->qrp ?? false))>
                QRP (zaškrtněte, pokud jste v závodě použili výkon QRP)
            </td>
        </tr>

        <tr>
            <td><strong>Volací znak *<br>Callsign *</strong></td>
            <td><input name="znacka" type="text" class="vkv-input vkv-input-bold" value="{{ $val('znacka', $e->znacka ?? '') }}" size="25"></td>
            <td width="100">Lokátor *<br>WWL *</td>
            <td><input name="lokator" type="text" class="vkv-input" value="{{ $val('lokator', $e->locator ?? '') }}" size="15"></td>
        </tr>

        <tr>
            <td colspan="4">
                <table width="100%" cellpadding="0" cellspacing="0" style="border:none;">
                    <tr>
                        <td style="border:none;">Počet QSO *</td>
                        <td style="border:none;"><input name="pocet" type="text" class="vkv-input" value="{{ $val('pocet', $e->pocet ?? 0, 0) }}" size="6"></td>
                        <td style="border:none;">Bodů za QSO</td>
                        <td style="border:none;"><input name="bodu_za_qso" type="text" class="vkv-input" value="{{ $val('bodu_za_qso', $e->bodu_za_qso ?? 0, 0) }}" size="6"></td>
                        <td style="border:none;">Násobiče *</td>
                        <td style="border:none;"><input name="nasobice" type="text" class="vkv-input" value="{{ $val('nasobice', $e->nasobice ?? 0, 0) }}" size="6"></td>
                        <td style="border:none;">Celkem bodů *</td>
                        <td style="border:none;"><input name="body" type="text" class="vkv-input vkv-input-bold" value="{{ $val('body', $e->body ?? 0, 0) }}" size="10" style="background-color: #ffffcc;"></td>
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
            <td><input name="mail" type="text" class="vkv-input" value="{{ $val('mail', $e->mail ?? '') }}" style="width: 280px;"></td>
            <td align="right">telefon</td>
            <td><input name="telefon" type="text" class="vkv-input" value="{{ $val('telefon', $e->telefon ?? '') }}" style="width: 200px;"></td>
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
                <a href="{{ route('edit_hlaseni') }}" style="color: #CC0000; text-decoration: underline;">vymazat formulář</a>
            </td>
            <td colspan="2" align="right">
                <input type="submit" name="Odeslat" value="Odeslat / Send" style="padding: 5px 20px; font-weight: bold; cursor: pointer;">
            </td>
        </tr>
    </table>
</form>
@endsection
