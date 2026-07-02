@extends('layouts.app')
@section('title', 'Dashboard – VKV PA')

@push('head')
@vite('resources/js/dashboard.js')
@endpush

@section('content')

<div class="dashboard-page" data-dashboard>
<div class="dashboard-top">
    <div>
        <div class="dashboard-kicker">Administrace</div>
        <h1 class="dashboard-title">Dashboard <span>{{ $rok }}</span></h1>
    </div>
    <div class="dashboard-year-switcher">
        <a href="{{ route('admin.dashboard', ['rok' => $rok - 1]) }}" class="btn btn-ghost px-2">← {{ $rok - 1 }}</a>
        @if ($rok < now()->year)
            <a href="{{ route('admin.dashboard', ['rok' => $rok + 1]) }}" class="btn btn-ghost px-2">{{ $rok + 1 }} →</a>
        @endif
    </div>
</div>

{{-- ── Stat karty ───────────────────────────────────────────────────────── --}}
<div class="mb-8 space-y-3">

    {{-- Řádek 1: přehledové počty --}}
    <div class="dashboard-stat-grid dashboard-stat-grid--overview">
        @foreach ([
            ['label' => 'Kol celkem',    'value' => $celkemKol,    'tone' => 'primary'],
            ['label' => "Kol {$rok}",    'value' => $kolaTento,    'tone' => 'teal'],
            ['label' => "Stanic {$rok}", 'value' => $znackyTento,  'tone' => 'amber'],
            ['label' => 'Stanic celkem', 'value' => $celkemZnacek, 'tone' => 'slate'],
        ] as $card)
            <div class="dashboard-stat-card dashboard-stat-card--{{ $card['tone'] }}">
                <div class="dashboard-stat-label">{{ $card['label'] }}</div>
                <div class="dashboard-stat-value">{{ $card['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Řádek 2: operativní a výkonnostní statistiky --}}
    <div class="dashboard-stat-grid dashboard-stat-grid--ops">

        {{-- Čekající na schválení – zvýrazněno pokud jsou nevyřízené záznamy --}}
        <a href="{{ route('deniky.index') }}"
           class="card dashboard-stat-card dashboard-stat-card--warning dashboard-stat-card--link {{ $cekajici > 0 ? 'is-active' : '' }}">
            <div class="dashboard-stat-row">
                <div class="dashboard-stat-label">Čeká na schválení {{ $rok }}</div>
                @if ($cekajici > 0)
                    <span class="dashboard-pulse" aria-hidden="true"></span>
                @endif
            </div>
            <div class="dashboard-stat-value">{{ $cekajici }}</div>
        </a>

        <div class="dashboard-stat-card dashboard-stat-card--primary">
            <div class="dashboard-stat-label">Průměrné body {{ $rok }}</div>
            <div class="dashboard-stat-value">{{ $avgBody > 0 ? \Illuminate\Support\Number::format($avgBody, 0) : '—' }}</div>
        </div>

        <div class="dashboard-stat-card dashboard-stat-card--teal">
            <div class="dashboard-stat-label">Průměrný počet QSO {{ $rok }}</div>
            <div class="dashboard-stat-value">{{ $avgQso > 0 ? $avgQso : '—' }}</div>
        </div>

    </div>
</div>

{{-- ── Grafy – řádek 1 ──────────────────────────────────────────────────── --}}
<div class="dashboard-chart-grid mb-6">

    <section class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h2>Účastníci per kolo</h2>
            <span>posledních 12</span>
        </div>
        <canvas id="chartKola" height="200"></canvas>
    </section>

    <section class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h2>Distribuce pásem</h2>
            <span>{{ $rok }}</span>
        </div>
        <canvas id="chartKategorie" height="200"></canvas>
    </section>

</div>

{{-- ── Graf: rok vs. rok ────────────────────────────────────────────────── --}}
<section class="dashboard-panel dashboard-panel--wide mb-8">
    <div class="dashboard-panel-header">
        <h2>Rok vs. rok</h2>
        <span>{{ $rok - 1 }} / {{ $rok }}</span>
    </div>
    <canvas id="chartRokVsRok" height="140"></canvas>
</section>

{{-- ── Přehled kol roku ─────────────────────────────────────────────────── --}}
<div class="dashboard-section-heading">
    <h2>Přehled kol {{ $rok }}</h2>
</div>

@if ($kolaRoku->isEmpty())
    <p class="mb-8 text-sm text-muted">Žádná kola pro rok {{ $rok }}.</p>
@else
    <div class="table-wrap dashboard-table-wrap mb-8">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kolo</th>
                    <th class="num">Datum</th>
                    <th class="num">Přihlášeno</th>
                    <th class="num">Schváleno</th>
                    <th class="num">Čeká</th>
                    <th>Stav</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kolaRoku as $kolo)
                    @php
                        $ceka  = $kolo->pocet_celkem - $kolo->pocet_schvalenych;
                        $pct   = $kolo->pocet_celkem > 0
                            ? round($kolo->pocet_schvalenych / $kolo->pocet_celkem * 100)
                            : 0;
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('kola.admin.edit', $kolo) }}" class="font-medium hover:underline">
                                {{ $kolo->name }}
                            </a>
                        </td>
                        <td class="num whitespace-nowrap text-sm text-muted">{{ $kolo->starts_at->format('j. n. Y') }}</td>
                        <td class="num">{{ $kolo->pocet_celkem }}</td>
                        <td class="num">
                            <div class="flex items-center justify-end gap-2">
                                <div class="dashboard-progress">
                                    <div class="dashboard-progress__bar" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="font-semibold">{{ $kolo->pocet_schvalenych }}</span>
                            </div>
                        </td>
                        <td class="num {{ $ceka > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-muted' }}">
                            {{ $ceka > 0 ? $ceka : '—' }}
                        </td>
                        <td>
                            <span class="badge {{ $kolo->state()->badgeClass() }}">{{ $kolo->state()->label() }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- ── Top 10 stanic ───────────────────────────────────────────────────── --}}
<div class="dashboard-section-heading">
    <h2>Top 10 stanic {{ $rok }}</h2>
</div>

@if ($top10->isEmpty())
    <p class="text-sm text-muted">Žádné výsledky pro rok {{ $rok }}.</p>
@else
    <div class="table-wrap dashboard-table-wrap mb-8">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>Značka</th>
                    <th>Jméno</th>
                    <th>Kategorie</th>
                    <th class="num">Body celkem</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $medalColors = ['dashboard-medal--gold', 'dashboard-medal--silver', 'dashboard-medal--bronze'];
                @endphp
                @foreach ($top10 as $i => $r)
                    <tr>
                        <td class="num"><span class="dashboard-medal {{ $medalColors[$i] ?? '' }}">{{ $i + 1 }}</span></td>
                        <td class="mono font-bold">{{ $r->callsign }}</td>
                        <td>{{ $r->name }}</td>
                        <td class="text-sm text-muted">{{ $kategorie->get($r->kategorie_id)?->name ?? '—' }}</td>
                        <td class="num font-semibold">{{ \Illuminate\Support\Number::format((int) $r->celkem, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
</div>

@push('scripts')
<script @cspNonce>
window.__dashboardConfig = {
    rok: {{ $rok }},
    rokPredchozi: {{ $rok - 1 }},
    trendKolaLabels: @json($trendKola->pluck('name')),
    trendKolaData: @json($trendKola->pluck('pocet')),
    katLabels: @json($kategorieData->map(fn ($r) => $r->band_name ?? 'Neznámé pásmo')->values()),
    katData: @json($kategorieData->pluck('pocet')),
    aktData: @json($kolaRoku->pluck('pocet_schvalenych')->values()),
    prevData: @json($trendPredchoziRok->pluck('pocet')->values()),
};
</script>
@endpush

@endsection
