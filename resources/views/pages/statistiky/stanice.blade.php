{{-- Veřejný profil stanice: souhrn, trend bodů a historie účastí napříč
     vyhodnocenými koly. Trend graf řídí statistiky.js (chartStaniceTrend). --}}
@extends('layouts.app')

@section('title', __('pages.stat.stanice_title', ['call' => $profil['znacka']]))
@section('meta_description', __('pages.stat.stanice_meta', ['call' => $profil['znacka'], 'kola' => $profil['pocetKol']]))

@push('head')
  @vite('resources/js/statistiky.js')
@endpush

@section('content')

<script @cspNonce>
window.__statConfig = {
    staniceTrend: @json($profil['trend']),
    t: { points: @json(__('pages.stat.unit_points')) },
};
</script>

<header class="mb-4">
  <div class="text-xs font-semibold uppercase tracking-wide text-muted">
    <a href="{{ route('statistiky.index') }}" class="underline hover:text-heading">{{ __('pages.stat.index_heading') }}</a>
  </div>
  <h1 class="text-2xl font-bold text-heading">{{ $profil['znacka'] }}</h1>
  <p class="text-sm text-muted">{{ __('pages.stat.stanice_subtitle') }}</p>
</header>

{{-- ── Souhrnné karty ──────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 mb-5">
  @foreach ([
    [__('pages.stat.s_rounds'),  $profil['pocetKol'], ''],
    [__('pages.stat.s_points'),  $profil['bodyCelkem'], ''],
    [__('pages.stat.s_qso'),     $profil['qsoCelkem'], ''],
    [__('pages.stat.s_best'),    $profil['nejlepsiPoradi'] ? $profil['nejlepsiPoradi'].'.' : '—', ''],
    [__('pages.stat.s_topscore'),$profil['nejlepsiSkore'], 'b.'],
  ] as [$label, $value, $unit])
  <div class="rounded-lg border border-line bg-surface p-3 text-center">
    <div class="text-2xl font-bold text-heading">{{ $value }}<span class="text-sm font-normal text-muted ml-1">{{ $unit }}</span></div>
    <div class="text-xs text-muted mt-0.5">{{ $label }}</div>
  </div>
  @endforeach
</div>

{{-- ── Trend bodů ──────────────────────────────────────────────────────── --}}
@if (count($profil['historie']) > 1)
<div class="rounded-lg border border-line bg-surface p-3 mb-5">
  <div class="text-sm font-semibold text-heading mb-2">{{ __('pages.stat.s_trend') }}</div>
  <div class="h-56"><canvas id="chartStaniceTrend"></canvas></div>
</div>
@endif

{{-- ── Historie účastí ─────────────────────────────────────────────────── --}}
<div class="section-head">{{ __('pages.stat.s_history') }}</div>
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>{{ __('pages.stat.col_round') }}</th>
        <th>{{ __('pages.stat.col_category') }}</th>
        <th class="num">QSO</th>
        <th class="num">{{ __('pages.stat.col_mult') }}</th>
        <th class="num">{{ __('pages.stat.col_points') }}</th>
        <th class="num">{{ __('pages.stat.col_place') }}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    @foreach (array_reverse($profil['historie']) as $h)
      <tr>
        <td class="font-medium"><a class="underline hover:text-heading" href="{{ route('statistiky.kolo', ['kolo' => $h['koloId']]) }}">{{ $h['kolo'] }}</a></td>
        <td class="text-xs text-muted">{{ $h['kategorie'] }}</td>
        <td class="num">{{ $h['pocet'] }}</td>
        <td class="num">{{ $h['multiplier'] }}</td>
        <td class="num font-bold">{{ $h['body'] }}</td>
        <td class="num">{{ $h['poradi'] > 0 ? $h['poradi'].'.' : '—' }}</td>
        <td>
          @if ($h['edihead_id'])
            <a class="text-xs underline hover:text-heading" href="{{ route('edi.vizualizace', ['head' => $h['edihead_id']]) }}" target="_blank" rel="noopener">{{ __('pages.stat.s_log') }}</a>
          @endif
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>

@endsection
