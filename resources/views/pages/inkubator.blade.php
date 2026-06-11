{{--
    Vizuální inkubátor – doplňkové tabulky EDI deníku: TOP ODX, nové násobiče
    a nezapočítaná QSO. Grafy a mapa s přehráváním se přestěhovaly na stránku
    Vizualizace (route edi.vizualizace); porovnání se soupeřem je na stránce
    Porovnání deníků (route edi.porovnani).
--}}
@extends('layouts.app')

@section('title', 'Vizuální inkubátor – ' . $pcall . ' – VKV PA')

@section('content')

{{-- ── Hlavička ────────────────────────────────────────────────────────── --}}
<h1 class="text-xl font-bold text-heading">🧪 Vizuální inkubátor – {{ $pcall }}</h1>
<p class="text-sm text-muted mb-4">
  {{ $homeLoc }} · doplňkové tabulky deníku ·
  <a href="{{ route('edi.vizualizace', ['head' => $head]) }}" class="underline hover:text-heading">zpět na vizualizaci</a>
  {{-- Odkaz na porovnání jen když existuje aspoň jeden soupeř z téhož kola
       a kategorie (a kolo už je uzavřené/vyhodnocené). --}}
  @if ($porovnaniDostupne)
    · <a href="{{ route('edi.porovnani', ['head' => $head]) }}" class="underline hover:text-heading">⚔️ Porovnání deníků</a>
  @endif
</p>

{{-- ── TOP ODX ─────────────────────────────────────────────────────────── --}}
<div class="section-head">TOP ODX – nejvzdálenější spojení</div>
@if ($odx === [])
  <p class="text-muted mb-4">Žádná spojení se spočítanou vzdáleností.</p>
@else
<div class="table-wrap mb-5">
  <table class="data-table">
    <thead>
      <tr>
        <th class="num">#</th>
        <th>Značka</th>
        <th>Lokátor</th>
        <th class="num">km</th>
        <th class="num">Azimut</th>
        <th>Čas</th>
        <th>Mód</th>
        <th class="num">Body</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($odx as $i => $o)
      <tr>
        <td class="num font-bold">{{ $i + 1 }}.</td>
        <td class="mono font-bold">{{ $o['call'] }}</td>
        <td class="mono">{{ $o['wwl'] }}</td>
        <td class="num font-bold">{{ $o['dist'] }}</td>
        <td class="num">{{ $o['azimut'] !== null ? $o['azimut'] . '°' : '—' }}</td>
        <td class="mono">{{ $o['cas'] }}</td>
        <td>{{ $o['mode'] }}</td>
        <td class="num">{{ $o['points'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

{{-- ── Nové násobiče ───────────────────────────────────────────────────── --}}
<div class="section-head">Nové násobiče (velké čtverce)</div>
@if ($nasobice === [])
  <p class="text-muted mb-4">Žádný nový násobič nad rámec vlastního čtverce {{ $homeSq }}.</p>
@else
<div class="table-wrap mb-2">
  <table class="data-table">
    <thead>
      <tr>
        <th class="num">Násobič č.</th>
        <th>Čtverec</th>
        <th>Čas</th>
        <th>První QSO</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($nasobice as $n)
      <tr>
        <td class="num font-bold">{{ $n['poradi'] }}</td>
        <td class="mono font-bold">{{ $n['square'] }}</td>
        <td class="mono">{{ $n['cas'] }}</td>
        <td class="mono">{{ $n['call'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
<p class="text-xs text-muted mb-5">Vlastní čtverec {{ $homeSq }} je násobič č. 1 automaticky (počítá se vždy, i bez QSO).</p>
@endif

{{-- ── Nezapočítaná QSO ────────────────────────────────────────────────── --}}
@if ($nezapocitana['celkem'] > 0)
<div class="section-head">Nezapočítaná a označená QSO</div>
<div class="table-wrap mb-2">
  <table class="data-table">
    <thead>
      <tr>
        <th>Značka</th>
        <th>Čas</th>
        <th>Důvod</th>
      </tr>
    </thead>
    <tbody>
    @foreach ($nezapocitana['radky'] as $r)
      <tr>
        <td class="mono font-bold">{{ $r['call'] }}</td>
        <td class="mono">{{ $r['cas'] }}</td>
        <td class="text-muted">{{ $r['duvod'] }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
<p class="text-xs text-muted mb-5">
  QSO mimo závodní okno či mimo den závodu se do skóre nepočítají. Duplicity označené v deníku se ve skóre počítají, uvádíme je jen pro kontrolu.
  @if ($nezapocitana['celkem'] > count($nezapocitana['radky']))
    Zobrazeno prvních {{ count($nezapocitana['radky']) }} z {{ $nezapocitana['celkem'] }} řádků.
  @endif
</p>
@endif

@endsection
