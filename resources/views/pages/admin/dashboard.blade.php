@extends('layouts.app')
@section('title', 'Dashboard – VKV PA')

@push('head')
@vite('resources/js/dashboard.js')
@endpush

@section('content')

<div class="mb-6 flex items-baseline justify-between gap-4">
    <h1>Dashboard <span class="text-muted font-normal text-base">{{ $rok }}</span></h1>
    <div class="flex gap-1 text-sm">
        <a href="{{ route('admin.dashboard', ['rok' => $rok - 1]) }}" class="btn-ghost px-2">← {{ $rok - 1 }}</a>
        @if ($rok < now()->year)
            <a href="{{ route('admin.dashboard', ['rok' => $rok + 1]) }}" class="btn-ghost px-2">{{ $rok + 1 }} →</a>
        @endif
    </div>
</div>

{{-- ── Stat karty ───────────────────────────────────────────────────────── --}}
<div class="mb-8 space-y-3">

    {{-- Řádek 1: přehledové počty --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['label' => 'Kol celkem',    'value' => $celkemKol],
            ['label' => "Kol {$rok}",    'value' => $kolaTento],
            ['label' => "Stanic {$rok}", 'value' => $znackyTento],
            ['label' => 'Stanic celkem', 'value' => $celkemZnacek],
        ] as $card)
            <div class="rounded-xl border border-line bg-surface p-4">
                <div class="text-3xl font-bold text-heading">{{ $card['value'] }}</div>
                <div class="mt-1 text-xs text-muted">{{ $card['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Řádek 2: operativní a výkonnostní statistiky --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">

        {{-- Čekající na schválení – zvýrazněno pokud jsou nevyřízené záznamy --}}
        <a href="{{ route('deniky.index') }}"
           class="rounded-xl border p-4 transition hover:border-brand
                  {{ $cekajici > 0
                      ? 'border-amber-400 bg-amber-50 dark:border-amber-500 dark:bg-amber-950/20'
                      : 'border-line bg-surface' }}">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="text-3xl font-bold {{ $cekajici > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-heading' }}">
                        {{ $cekajici }}
                    </div>
                    <div class="mt-1 text-xs text-muted">Čeká na schválení {{ $rok }}</div>
                </div>
                @if ($cekajici > 0)
                    <span class="relative mt-1 flex h-2.5 w-2.5 shrink-0">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                    </span>
                @endif
            </div>
        </a>

        <div class="rounded-xl border border-line bg-surface p-4">
            <div class="text-3xl font-bold text-heading">
                {{ $avgBody > 0 ? number_format($avgBody, 0, ',', "\u{00a0}") : '—' }}
            </div>
            <div class="mt-1 text-xs text-muted">Průměrné body {{ $rok }}</div>
        </div>

        <div class="rounded-xl border border-line bg-surface p-4">
            <div class="text-3xl font-bold text-heading">{{ $avgQso > 0 ? $avgQso : '—' }}</div>
            <div class="mt-1 text-xs text-muted">Průměrný počet QSO {{ $rok }}</div>
        </div>

    </div>
</div>

{{-- ── Grafy – řádek 1 ──────────────────────────────────────────────────── --}}
<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

    <div class="rounded-xl border border-line bg-surface p-4">
        <h2 class="mb-3 text-sm font-semibold text-heading">Účastníci per kolo (posledních 12)</h2>
        <canvas id="chartKola" height="200"></canvas>
    </div>

    <div class="rounded-xl border border-line bg-surface p-4">
        <h2 class="mb-3 text-sm font-semibold text-heading">Distribuce kategorií {{ $rok }}</h2>
        <canvas id="chartKategorie" height="200"></canvas>
    </div>

</div>

{{-- ── Graf: rok vs. rok ────────────────────────────────────────────────── --}}
<div class="mb-8 rounded-xl border border-line bg-surface p-4">
    <h2 class="mb-3 text-sm font-semibold text-heading">Rok vs. rok – {{ $rok - 1 }} / {{ $rok }}</h2>
    <canvas id="chartRokVsRok" height="140"></canvas>
</div>

{{-- ── Přehled kol roku ─────────────────────────────────────────────────── --}}
<h2>Přehled kol {{ $rok }}</h2>

@if ($kolaRoku->isEmpty())
    <p class="mb-8 text-sm text-muted">Žádná kola pro rok {{ $rok }}.</p>
@else
    <div class="table-wrap mb-8">
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
                                {{ $kolo->nazev }}
                            </a>
                        </td>
                        <td class="num whitespace-nowrap text-sm text-muted">{{ $kolo->datum_konani->format('j. n. Y') }}</td>
                        <td class="num">{{ $kolo->pocet_celkem }}</td>
                        <td class="num">
                            <div class="flex items-center justify-end gap-2">
                                <div class="h-1 w-12 overflow-hidden rounded-full bg-line">
                                    <div class="h-full rounded-full bg-brand transition-all" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="font-semibold">{{ $kolo->pocet_schvalenych }}</span>
                            </div>
                        </td>
                        <td class="num {{ $ceka > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-muted' }}">
                            {{ $ceka > 0 ? $ceka : '—' }}
                        </td>
                        <td>
                            <span class="badge {{ $kolo->stav()->badgeClass() }}">{{ $kolo->stav()->label() }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- ── Top 10 stanic ───────────────────────────────────────────────────── --}}
<h2>Top 10 stanic {{ $rok }}</h2>

@if ($top10->isEmpty())
    <p class="text-sm text-muted">Žádné výsledky pro rok {{ $rok }}.</p>
@else
    <div class="table-wrap mb-8">
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
                    $medalColors = ['text-amber-500', 'text-zinc-400', 'text-orange-600'];
                @endphp
                @foreach ($top10 as $i => $r)
                    <tr>
                        <td class="num font-bold {{ $medalColors[$i] ?? 'text-muted' }}">{{ $i + 1 }}</td>
                        <td class="mono font-bold">{{ $r->znacka }}</td>
                        <td>{{ $r->jmeno }}</td>
                        <td class="text-sm text-muted">{{ $kategorie->get($r->kategorie_id)?->nazev ?? '—' }}</td>
                        <td class="num font-semibold">{{ number_format((int) $r->celkem, 0, ',', "\u{00a0}") }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@push('scripts')
<script @cspNonce>
window.__dashboardConfig = {
    rok: {{ $rok }},
    rokPredchozi: {{ $rok - 1 }},
    trendKolaLabels: @json($trendKola->pluck('nazev')),
    trendKolaData: @json($trendKola->pluck('pocet')),
    katLabels: @json($kategorieData->map(fn ($r) => $kategorie[$r->id_kategorie]?->nazev ?? "kat {$r->id_kategorie}")->values()),
    katData: @json($kategorieData->pluck('pocet')),
    aktData: @json($kolaRoku->pluck('pocet_schvalenych')->values()),
    prevData: @json($trendPredchoziRok->pluck('pocet')->values()),
};
</script>
@endpush

@endsection
