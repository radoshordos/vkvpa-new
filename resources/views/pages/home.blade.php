@extends('layouts.app')

@section('title', __('pages.home.title'))

@section('content')

{{-- ── Aktuální / nadcházející kolo ──────────────────────────────── --}}
@if ($kolo)
<div class="card mb-6 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">

        {{-- Info o kole --}}
        <div class="min-w-0 flex-1">
            <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">
                @if ($state === 'upcoming')  {{ __('pages.home.state_upcoming') }}
                @elseif ($state === 'running') {{ __('pages.home.state_running') }}
                @elseif ($state === 'deadline') {{ __('pages.home.state_deadline') }}
                @elseif ($state === 'evaluating') {{ __('pages.home.state_evaluating') }}
                @else {{ __('pages.home.state_evaluated') }}
                @endif
            </div>
            <h2 class="!mb-2 !mt-0 text-xl font-bold">{{ $kolo->nazev }}</h2>

            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                <dt class="text-muted">{{ __('pages.home.contest_date') }}</dt>
                <dd class="font-medium">
                    {{ $kolo->datum_konani->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' }}
                    <span class="font-normal text-muted" data-local-time="{{ $kolo->datum_konani->getTimestamp() }}" data-local-suffix="{{ __('pages.home.local_time_suffix') }}"></span>
                </dd>

                @if ($kolo->datum_uzaverky)
                <dt class="text-muted">{{ __('pages.home.deadline') }}</dt>
                <dd>
                    {{ $kolo->datum_uzaverky->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' }}
                    <span class="text-muted" data-local-time="{{ $kolo->datum_uzaverky->getTimestamp() }}" data-local-suffix="{{ __('pages.home.local_time_suffix') }}"></span>
                </dd>
                @endif

                @if ($kolo->vyhodnoceno)
                <dt class="text-muted">{{ __('pages.home.evaluated_at') }}</dt>
                <dd>{{ $kolo->vyhodnoceno->isoFormat('D. MMMM YYYY HH:mm') }}</dd>
                @endif
            </dl>

            @if ($state === 'evaluating')
            <p class="mt-3 inline-flex items-center gap-1.5 text-sm text-muted">
                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-amber-400"></span>
                {{ __('pages.home.evaluating_note') }}
            </p>
            @endif
        </div>

        {{-- Odpočítávání --}}
        @if ($countdownTarget)
        <div class="shrink-0 rounded-xl border border-line bg-surface-2 px-5 py-4 text-center">
            <div id="js-countdown" class="font-mono text-3xl font-bold tabular-nums text-heading">--:--:--</div>
            <div class="mt-1 text-xs text-muted">
                @if ($state === 'upcoming') {{ __('pages.home.countdown_to_start') }}
                @elseif ($state === 'running') {{ __('pages.home.countdown_to_end') }}
                @else {{ __('pages.home.countdown_to_deadline') }}
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Upload window info strip --}}
    @php
        $uploadDate = $kolo->datum_konani->isoFormat('D. M.');
        $uploadDeadline = $kolo->datum_uzaverky?->isoFormat('D. M. YYYY') ?? '?';
    @endphp
    <div class="mt-4 flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2 text-xs text-muted">
        <span class="shrink-0 font-semibold text-heading">{{ __('pages.home.upload_window_label') }}:</span>
        <span>
            @if ($state === 'upcoming')
                {{ __('pages.home.upload_window_upcoming', ['date' => $uploadDate, 'deadline' => $uploadDeadline]) }}
            @elseif (in_array($state, ['running', 'deadline']))
                <span class="font-medium text-green-600 dark:text-green-400">
                    {{ __('pages.home.upload_window_open', ['deadline' => $uploadDeadline]) }}
                </span>
            @elseif ($state === 'evaluating')
                {{ __('pages.home.upload_window_closed_auth') }}
            @else
                {{ __('pages.home.upload_window_evaluated') }}
            @endif
        </span>
    </div>

    {{-- Počet zatím přijatých hlášení (během příjmu) --}}
    @if (in_array($state, ['running', 'deadline']))
    <p class="mt-3 text-sm text-muted">
        {{ trans_choice('pages.home.received_count', $vysledky->count(), ['count' => $vysledky->count()]) }}
    </p>
    @endif

    {{-- CTA tlačítka --}}
    <div class="mt-4 flex flex-wrap gap-3">
        @if (in_array($state, ['running', 'deadline']))
            <a href="{{ route('hlaseni.index') }}" class="btn btn-primary">{{ __('pages.home.btn_submit') }}</a>
        @endif
        @if (in_array($state, ['evaluating', 'evaluated', 'running', 'deadline']))
            <a href="{{ route('pribezne_vysledky', ['kolo' => $kolo->id]) }}" class="btn">{{ __('pages.home.btn_interim') }}</a>
        @endif
        @if ($state === 'evaluated')
            <a href="{{ route('vysledkova_listina', ['kolo' => $kolo->id]) }}" class="btn btn-primary">{{ __('pages.home.btn_results') }}</a>
        @endif
        <a href="{{ route('diskuse.show', $kolo->id) }}" class="btn">
            {{ __('pages.home.btn_discussion') }}@if ($diskuseCount > 0) ({{ $diskuseCount }})@endif
        </a>
    </div>
</div>
@endif

{{-- ── Poslední vyhodnocené kolo (jen když hero ukazuje nadcházející) ── --}}
@if ($posledniVyhodnocene)
<div class="card mb-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('pages.home.last_evaluated_heading') }}</div>
            <div class="mt-0.5 font-bold">{{ $posledniVyhodnocene->nazev }}</div>
            @if ($posledniVyhodnocene->vyhodnoceno)
            <div class="text-xs text-muted">{{ __('pages.home.evaluated_at') }}: {{ $posledniVyhodnocene->vyhodnoceno->locale(app()->getLocale())->isoFormat('D. M. YYYY HH:mm') }}</div>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('vysledkova_listina', ['kolo' => $posledniVyhodnocene->id]) }}" class="btn btn-primary">{{ __('pages.home.btn_results') }}</a>
            <a href="{{ route('diskuse.show', $posledniVyhodnocene->id) }}" class="btn">{{ __('pages.home.btn_discussion') }}</a>
        </div>
    </div>
</div>
@endif

{{-- ── Live výsledky (průběžné, auto-refresh) ────────────────────── --}}
@if ($liveMode && $vysledky->isNotEmpty())
<div class="mb-6" id="live-results-section">
    <div class="section-head flex items-center justify-between">
        <span>
            <x-badge variant="warn" class="mr-2">LIVE</x-badge>
            {{ __('pages.home.live_heading') }}
        </span>
        <span class="text-xs font-normal text-white/85">
            {{ __('pages.home.live_refresh_in') }} <span id="js-refresh-counter">60</span> s
        </span>
    </div>

    @foreach ($vysledky->groupBy('id_kategorie') as $katId => $radky)
    <div class="mb-1 mt-3 text-sm font-semibold text-muted">
        {{ $kategorie[$katId]->nazev ?? ('Kategorie '.$katId) }}
    </div>
    <div class="table-wrap mb-4">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">{{ __('pages.hlaseni.col_pos') }}</th>
                    <th>{{ __('pages.hlaseni.col_callsign') }}</th>
                    <th>{{ __('pages.hlaseni.col_locator') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_qso') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_mult') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_total') }}</th>
                    <th>{{ __('pages.hlaseni.col_name_note') }}</th>
                    <th>{{ __('pages.hlaseni.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($radky as $i => $r)
                <tr @class(['row-pending' => ! $r->schvaleno])>
                    <td class="num font-bold">{{ $i + 1 }}.</td>
                    <td class="mono font-bold">{{ $r->znacka }}@if ($r->qrp)<x-badge variant="qrp" class="ml-1">QRP</x-badge>@elseif ($r->lp)<x-badge variant="lp" class="ml-1">LP</x-badge>@endif</td>
                    <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                    <td class="num">{{ (int) $r->pocet }}</td>
                    <td class="num">{{ (int) $r->nasobice }}</td>
                    <td class="num font-bold">{{ (int) $r->body }}</td>
                    <td class="text-muted text-sm">{{ $r->jmeno }}@if ($r->poznamka) <i>({{ $r->poznamka }})</i>@endif</td>
                    <td>
                        @if ($r->schvaleno)
                            <x-badge variant="ok">{{ __('pages.hlaseni.status_ok') }}</x-badge>
                        @else
                            <x-badge variant="warn">{{ __('pages.hlaseni.status_pending') }}</x-badge>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endforeach
</div>
@elseif ($liveMode)
<div class="card mb-6 p-4 text-sm text-muted">
    {{ __('pages.home.live_no_entries') }}
</div>
@endif

{{-- ── Rychlé přístupy ────────────────────────────────────────────── --}}
<div class="section-head">{{ __('pages.home.quick_links') }}</div>
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    <a href="{{ route('hlaseni.index') }}" class="card block p-4 text-center hover:border-brand hover:bg-surface-2 transition-colors">
        <div class="text-2xl mb-1">📋</div>
        <div class="text-sm font-semibold">{{ __('pages.home.ql_submit') }}</div>
        <div class="text-xs text-muted mt-0.5">{{ __('pages.home.ql_submit_desc') }}</div>
    </a>
    <a href="{{ route('pribezne_vysledky') }}" class="card block p-4 text-center hover:border-brand hover:bg-surface-2 transition-colors">
        <div class="text-2xl mb-1">📊</div>
        <div class="text-sm font-semibold">{{ __('pages.home.ql_interim') }}</div>
        <div class="text-xs text-muted mt-0.5">{{ __('pages.home.ql_interim_desc') }}</div>
    </a>
    <a href="{{ route('vysledkova_listina') }}" class="card block p-4 text-center hover:border-brand hover:bg-surface-2 transition-colors">
        <div class="text-2xl mb-1">🏆</div>
        <div class="text-sm font-semibold">{{ __('pages.home.ql_results') }}</div>
        <div class="text-xs text-muted mt-0.5">{{ __('pages.home.ql_results_desc') }}</div>
    </a>
    <a href="{{ route('rocni_vysledky') }}" class="card block p-4 text-center hover:border-brand hover:bg-surface-2 transition-colors">
        <div class="text-2xl mb-1">📅</div>
        <div class="text-sm font-semibold">{{ __('pages.home.ql_yearly') }}</div>
        <div class="text-xs text-muted mt-0.5">{{ __('pages.home.ql_yearly_desc') }}</div>
    </a>
</div>

{{-- ── Z diskuse (poslední příspěvky napříč koly) ─────────────────── --}}
@if ($posledniPrispevky->isNotEmpty())
<div class="mt-8">
    <div class="section-head flex items-center justify-between">
        <span>{{ __('pages.home.discussion_heading') }}</span>
        <a href="{{ route('diskuse.index') }}" class="text-xs font-normal text-white/85 underline hover:text-white">{{ __('pages.home.discussion_all') }}</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($posledniPrispevky as $p)
        <a href="{{ route('diskuse.show', $p->kolo_id) }}" class="card block p-4 hover:border-brand hover:bg-surface-2 transition-colors">
            <div class="flex items-baseline justify-between gap-2">
                <span class="mono text-sm font-bold">{{ $p->znacka }}</span>
                <span class="text-xs text-muted">{{ $p->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
            </div>
            <div class="mt-0.5 text-xs text-muted">{{ $p->kolo->nazev }}</div>
            <p class="mt-2 text-sm">{{ \Illuminate\Support\Str::limit($p->text, 120) }}</p>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- ── Nadcházející kola ───────────────────────────────────────────── --}}
@if ($upcomingRounds->isNotEmpty())
<div class="mt-8">
    <div class="section-head">{{ __('pages.home.upcoming_heading') }}</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('pages.kola.col_name') }}</th>
                    <th>{{ __('pages.kola.col_date') }}</th>
                    <th>{{ __('pages.kola.col_deadline') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($upcomingRounds as $r)
                <tr>
                    <td class="font-medium">{{ $r->nazev }}</td>
                    {{-- Stejný formát jako na stránce kol: den + datum + čas UTC --}}
                    <td class="whitespace-nowrap">
                        {{ $r->datum_konani->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' }}
                        <span class="text-muted" data-local-time="{{ $r->datum_konani->getTimestamp() }}" data-local-suffix="{{ __('pages.home.local_time_suffix') }}"></span>
                    </td>
                    <td class="whitespace-nowrap text-muted">
                        {{ $r->datum_uzaverky ? $r->datum_uzaverky->locale(app()->getLocale())->isoFormat('dddd D. M. YYYY HH:mm').' UTC' : '—' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script @cspNonce>
(function () {
    'use strict';

    {{-- Odpočítávání do začátku závodu nebo uzávěrky --}}
    @if ($countdownTarget)
    var countdownEl = document.getElementById('js-countdown');
    var target = {{ $countdownTarget->getTimestamp() }} * 1000;

    {{-- Auto-přepnutí: po doběhnutí odpočtu se stránka přenačte a server
         vykreslí nový stav kola. Reload se vyzbrojí jen tehdy, když byl cíl
         při načtení v budoucnu – ochrana proti reload smyčce (posun hodin). --}}
    var reloadArmed = target > Date.now();

    function pad(n) { return String(n).padStart(2, '0'); }

    function updateCountdown() {
        var diff = target - Date.now();
        if (diff <= 0) {
            countdownEl.textContent = '00:00:00';
            if (reloadArmed) {
                reloadArmed = false;
                setTimeout(function () { window.location.reload(); }, 3000);
            }
            return;
        }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        countdownEl.textContent = d > 0
            ? d + 'd ' + pad(h) + ':' + pad(m) + ':' + pad(s)
            : pad(h) + ':' + pad(m) + ':' + pad(s);
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
    @endif

    {{-- Auto-refresh live výsledků --}}
    @if ($liveMode)
    var refreshEl = document.getElementById('js-refresh-counter');
    var seconds = 60;

    setInterval(function () {
        seconds -= 1;
        if (refreshEl) refreshEl.textContent = seconds;
        if (seconds <= 0) { window.location.reload(); }
    }, 1000);
    @endif
}());
</script>
@endpush

@endsection
