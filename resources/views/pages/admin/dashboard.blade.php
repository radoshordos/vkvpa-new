@extends('layouts.app')
@section('title', 'Dashboard – VKV PA')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
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

{{-- ── Stat karty ──────────────────────────────────────────────────────── --}}
<div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach ([
        ['label' => 'Kol celkem',       'value' => $celkemKol],
        ['label' => "Kol {$rok}",       'value' => $kolaTento],
        ['label' => "Stanic {$rok}",    'value' => $znackyTento],
        ['label' => 'Stanic celkem',    'value' => $celkemZnacek],
    ] as $card)
        <div class="rounded-xl border border-line bg-surface p-4">
            <div class="text-3xl font-bold text-heading">{{ $card['value'] }}</div>
            <div class="mt-1 text-xs text-muted">{{ $card['label'] }}</div>
        </div>
    @endforeach
</div>

{{-- ── Grafy ───────────────────────────────────────────────────────────── --}}
<div class="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Graf: účastníci per kolo --}}
    <div class="rounded-xl border border-line bg-surface p-4">
        <h2 class="mb-3 text-sm font-semibold text-heading">Účastníci per kolo (posledních 12)</h2>
        <canvas id="chartKola" height="200"></canvas>
    </div>

    {{-- Graf: kategorie --}}
    <div class="rounded-xl border border-line bg-surface p-4">
        <h2 class="mb-3 text-sm font-semibold text-heading">Distribuce kategorií {{ $rok }}</h2>
        <canvas id="chartKategorie" height="200"></canvas>
    </div>

</div>

{{-- ── Top 10 stanic ───────────────────────────────────────────────────── --}}
<h2 class="mb-3 text-sm font-semibold text-heading">Top 10 stanic {{ $rok }}</h2>

@if ($top10->isEmpty())
    <p class="text-muted text-sm">Žádné výsledky pro rok {{ $rok }}.</p>
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
                @foreach ($top10 as $i => $r)
                    <tr>
                        <td class="num text-muted">{{ $i + 1 }}</td>
                        <td class="mono font-bold">{{ $r->znacka }}</td>
                        <td>{{ $r->jmeno }}</td>
                        <td class="text-sm text-muted">{{ $kategorie->get($r->kategorie_id)?->nazev ?? '—' }}</td>
                        <td class="num font-semibold">{{ number_format((int) $r->celkem, 0, ',', '\u{00a0}') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@push('scripts')
<script>
(function () {
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#a1a1aa' : '#71717a';
    const brandColor = '#6366f1';

    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;

    // Graf: účastníci per kolo
    new Chart(document.getElementById('chartKola'), {
        type: 'bar',
        data: {
            labels: @json($trendKola->pluck('nazev')),
            datasets: [{
                label: 'Účastníci',
                data: @json($trendKola->pluck('pocet')),
                backgroundColor: brandColor + 'cc',
                borderRadius: 4,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { ticks: { maxRotation: 45 } },
            },
        },
    });

    // Graf: distribuce kategorií
    const katLabels = @json($kategorieData->map(fn ($r) => $kategorie[$r->id_kategorie]?->nazev ?? "kat {$r->id_kategorie}")->values());
    const katData   = @json($kategorieData->pluck('pocet'));
    const palette   = ['#6366f1','#8b5cf6','#a78bfa','#c4b5fd','#ddd6fe','#ede9fe','#4f46e5','#7c3aed'];

    new Chart(document.getElementById('chartKategorie'), {
        type: 'doughnut',
        data: {
            labels: katLabels,
            datasets: [{
                data: katData,
                backgroundColor: palette,
                borderWidth: 2,
                borderColor: isDark ? '#18181b' : '#ffffff',
            }],
        },
        options: {
            plugins: { legend: { position: 'right' } },
            cutout: '65%',
        },
    });
})();
</script>
@endpush

@endsection
