{{-- Formulář hlášení (Fáze 6). Plná vizuální parita + JS dopočty → 6c. --}}
@extends('layouts.app')

@section('title', 'Odeslat deník – VKV PA')

@section('content')
<h1>Odeslat deník / Log import</h1>

@if (session('announcement'))
  <p class="green">{{ session('announcement') }}</p>
@endif
@if ($errors->any())
  <div class="red">
    @foreach ($errors->all() as $e)
      {{ $e }}<br>
    @endforeach
  </div>
@endif

@php $p = session('edi_prefill', []); $e = $edit ?? null; @endphp

@if (! $kolo)
  <p class="red">Není otevřené žádné kolo závodu.</p>
@else
  <p>Kolo: <strong>{{ $kolo->nazev }}</strong></p>

  <form action="{{ $e ? route('hlaseni.update', $e->id) : route('hlaseni.store') }}" method="post">
    @csrf
    @if ($e) @method('PUT') @endif
    <input type="hidden" name="kolo" value="{{ $kolo->id }}">
    <input type="hidden" name="EDI" value="{{ $p['EDI'] ?? ($e->EDI ?? 0) }}">
    <input type="hidden" name="EDIID" value="{{ $p['EDIID'] ?? ($e->EDI_ID ?? 0) }}">

    <table class="form">
      <tr><td>Kategorie</td><td>
        <select name="kategorie">
          @foreach ($kategorie as $k)
            <option value="{{ $k->id }}" @selected(old('kategorie', $e->id_kategorie ?? '') == $k->id)>{{ $k->nazev }}</option>
          @endforeach
        </select>
      </td></tr>
      <tr><td>Značka</td><td><input type="text" name="znacka" value="{{ old('znacka', $p['znacka'] ?? ($e->znacka ?? '')) }}"></td></tr>
      <tr><td>Lokátor</td><td><input type="text" name="lokator" value="{{ old('lokator', $p['lokator'] ?? ($e->locator ?? '')) }}"></td></tr>
      <tr><td>Počet QSO</td><td><input type="number" name="pocet" value="{{ old('pocet', $p['pocet'] ?? ($e->pocet ?? '')) }}"></td></tr>
      <tr><td>Bodů za QSO</td><td><input type="number" name="bodu_za_qso" value="{{ old('bodu_za_qso', $p['bodu_za_qso'] ?? ($e->bodu_za_qso ?? '')) }}"></td></tr>
      <tr><td>Násobiče</td><td><input type="number" name="nasobice" value="{{ old('nasobice', $p['nasobice'] ?? ($e->nasobice ?? '')) }}"></td></tr>
      <tr><td>Body celkem</td><td><input type="number" name="body" value="{{ old('body', $p['body'] ?? ($e->body ?? '')) }}"></td></tr>
      <tr><td>Jméno</td><td><input type="text" name="jmeno" value="{{ old('jmeno', $p['jmeno'] ?? ($e->jmeno ?? '')) }}"></td></tr>
      <tr><td>E-mail</td><td><input type="text" name="mail" value="{{ old('mail', $p['mail'] ?? ($e->mail ?? '')) }}"></td></tr>
      <tr><td>Telefon</td><td><input type="text" name="telefon" value="{{ old('telefon', $p['telefon'] ?? ($e->telefon ?? '')) }}"></td></tr>
      <tr><td>Poznámka</td><td><input type="text" name="poznamka" value="{{ old('poznamka', $e->poznamka ?? '') }}"></td></tr>
      <tr><td>Soapbox</td><td><textarea name="soapbox">{{ old('soapbox', $e->soapbox ?? '') }}</textarea></td></tr>
      <tr><td colspan="2"><input type="submit" name="Odeslat" value="Odeslat / Send"></td></tr>
    </table>
  </form>

  <h2>Podaná hlášení</h2>
  <table class="vypis">
    <tr><th>Značka</th><th>Lokátor</th><th>QSO</th><th>Body</th></tr>
    @foreach ($hlaseni as $h)
      <tr class="{{ $h->schvaleno ? 'schvaleno1' : 'neschvaleno' }}">
        <td>{{ $h->znacka }}</td><td>{{ $h->locator }}</td><td>{{ $h->pocet }}</td><td>{{ $h->body }}</td>
      </tr>
    @endforeach
  </table>
@endif
@endsection
