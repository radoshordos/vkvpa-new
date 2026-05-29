@extends('layouts.app')
@section('title', 'Výsledková listina – VKV PA')
@section('content')
<h1>Výsledková listina / Results</h1>

<form method="get" action="{{ route('vysledkova_listina') }}">
  <table class="form">
    <tr>
      <td>
        <select name="kolo" onchange="this.form.submit()">
          @foreach ($kola as $k)
            <option value="{{ $k->id }}" @selected($kolo && $k->id === $kolo->id)>{{ $k->nazev }} ({{ $k->datum_konani?->format('j.n.Y') }})</option>
          @endforeach
        </select>
      </td>
      <td><label><input type="checkbox" name="qrp" value="1" onchange="this.form.submit()">QRP</label></td>
      <td><input type="submit" value="Vypsat výsledkovou listinu"></td>
    </tr>
  </table>
</form>

<table class="vypis">
  <tr><th>pořadí</th><th>značka</th><th>lokátor</th><th>QSO</th><th>body</th></tr>
  @foreach ($radky as $r)
    <tr><td>{{ $r->poradi }}</td><td>{{ $r->znacka }}</td><td>{{ $r->locator }}</td><td>{{ $r->pocet }}</td><td>{{ $r->body }}</td></tr>
  @endforeach
</table>
@endsection
