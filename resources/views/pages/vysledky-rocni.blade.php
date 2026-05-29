@extends('layouts.app')
@section('title', 'Roční výsledky – VKV PA')
@section('content')
<h1>Roční výsledky / Year results</h1>

<form method="get" action="{{ route('rocni_vysledky') }}">
  <table class="form">
    <tr>
      <td>
        <select name="rok" onchange="this.form.submit()">
          @for ($y = (int) date('Y'); $y >= 2006; $y--)
            <option value="{{ $y }}" @selected($y === $rok)>{{ $y }}</option>
          @endfor
        </select>
      </td>
      <td><label><input type="checkbox" name="qrp" value="1" onchange="this.form.submit()">QRP</label></td>
      <td><input type="submit" value="Vypsat roční výsledky"></td>
    </tr>
  </table>
</form>

<h2>Výsledková listina za rok {{ $rok }}</h2>

@forelse ($vysledky as $kategorieId => $radky)
  <h3>{{ $kategorie[$kategorieId]->nazev ?? ('Kategorie ' . $kategorieId) }}</h3>
  <table class="vypis">
    <tr><th>pořadí</th><th>značka</th><th>celkem</th></tr>
    @foreach ($radky as $i => $r)
      <tr><td>{{ $i + 1 }}</td><td>{{ $r->znacka }}</td><td>{{ (int) $r->celkem }}</td></tr>
    @endforeach
  </table>
@empty
  <p class="small">Pro tento rok nejsou žádné vyhodnocené výsledky.</p>
@endforelse
@endsection
