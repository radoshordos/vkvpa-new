@extends('layouts.app')
@section('title', 'EDI debug – VKV PA')

@push('head')
<style>
    /* Stavové proužky řádků a sticky hlavička tabulky QSO. */
    .edx-qso thead th { position: sticky; top: 0; }
    .edx-qso tr.r-ok    td:first-child { box-shadow: inset 3px 0 0 var(--ok); }
    .edx-qso tr.r-warn  td:first-child { box-shadow: inset 3px 0 0 var(--warn); }
    .edx-qso tr.r-skip  td:first-child { box-shadow: inset 3px 0 0 var(--muted); }
    .edx-qso tr.r-skip  td { color: var(--muted); }
</style>
@endpush

@section('content')
@php
    $fmtDate = static fn (string $d): string => strlen($d) === 6
        ? substr($d, 4, 2).'.'.substr($d, 2, 2).'.'.substr($d, 0, 2)
        : ($d !== '' ? $d : '—');
    $fmtTime = static fn (string $t): string => strlen($t) === 4
        ? substr($t, 0, 2).':'.substr($t, 2, 2)
        : ($t !== '' ? $t : '—');
@endphp

<h1>EDI debug – kontrola bodování</h1>
<p class="max-w-prose text-sm text-muted">
    Nahraj EDI deník a zkontroluj řádek po řádku, jak vzniká skóre – které QSO se započítává,
    které spadá mimo závodní okno či den a které přináší nový násobič. Slouží jen pro náhled,
    do databáze se nic neukládá.
</p>

@if ($errors->any())
    <div class="alert alert-error mt-3">
        <strong>Soubor se nepodařilo zpracovat.</strong>
        <p class="mt-1">{{ $errors->first('upload') }}</p>
        @if (session('lineErrors'))
            <ul class="mt-2 list-disc pl-5">
                @foreach (session('lineErrors') as $le)
                    <li><code>{{ $le }}</code></li>
                @endforeach
            </ul>
        @endif
    </div>
@endif

<form class="card mt-4 mb-5 flex flex-wrap items-end gap-4 p-4" action="{{ route('edi.debug.store') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="field mb-0">
        <span class="label">EDI soubor</span>
        <input type="file" name="upload" accept=".edi,.txt" class="text-sm" required>
        <p class="mt-1 text-xs text-muted">Max 500 kB. Analyzuje se lokálně, nic se neukládá.</p>
    </div>
    <button type="submit" class="btn btn-primary">Analyzovat deník</button>
</form>

@if ($report)
    {{-- Hlavička deníku --}}
    <section class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @php
            $cards = [
                ['Značka', $report->call !== '' ? $report->call : '—', null],
                ['Lokátor', $report->locator !== '' ? $report->locator : '—', 'čtverec '.($report->homeSquare !== '' ? $report->homeSquare : '—')],
                ['Den závodu', $fmtDate($report->contestDay), null],
                ['Pásmo / sekce', $report->band !== '' ? $report->band : '—', $report->section !== '' ? $report->section : '—'],
                ['Výkon', $report->power.' W', $report->qrp ? 'QRP' : null],
                ['Závodní okno (UTC)', $fmtTime($report->windowFrom).'–'.$fmtTime($report->windowTo), null],
            ];
        @endphp
        @foreach ($cards as [$k, $v, $sub])
            <div class="card p-3">
                <span class="block text-xs uppercase tracking-wide text-muted">{{ $k }}</span>
                <span class="block text-base font-bold text-ink">{{ $v }}@if ($sub)<small class="ml-1 font-normal text-muted">{{ $sub }}</small>@endif</span>
            </div>
        @endforeach
    </section>

    {{-- Skóre --}}
    <section class="mb-5 rounded-xl bg-brand p-5 text-brand-fg">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex flex-col leading-none">
                <b class="text-3xl">{{ $report->boduZaQso }}</b>
                <span class="mt-1 text-xs uppercase tracking-wide opacity-80">body za spojení</span>
            </div>
            <span class="text-xl opacity-60">×</span>
            <div class="flex flex-col leading-none">
                <b class="text-3xl">{{ $report->nasobice }}</b>
                <span class="mt-1 text-xs uppercase tracking-wide opacity-80">násobič</span>
            </div>
            <span class="text-xl opacity-60">=</span>
            <div class="flex flex-col leading-none">
                <b class="text-4xl text-amber-300">{{ $report->body }}</b>
                <span class="mt-1 text-xs uppercase tracking-wide opacity-80">bodů</span>
            </div>
        </div>
        <p class="mt-3 text-xs opacity-80">
            Body za spojení = přepočítáno z lokátorů {{ $report->pocet }} započtených QSO
            (vlastní čtverec 2, sousední 3, každý další pás o bod víc; QSO-Points z deníku se ignoruje).
            Násobič = {{ $report->nasobice - 1 }} různých cizích velkých čtverců + 1 domácí.
        </p>
    </section>

    {{-- Souhrn parsování a vyloučení --}}
    <section class="mb-5 flex flex-wrap gap-2">
        <span class="badge badge-brand">deklarováno <b>{{ $report->declaredTotal }}</b></span>
        <span class="badge badge-brand">naparsováno <b>{{ $report->parsedCount }}</b></span>
        <span class="badge badge-ok">započteno <b>{{ $report->pocet }}</b></span>
        @if ($report->excludedOutOfWindow)<span class="badge badge-warn">mimo okno <b>{{ $report->excludedOutOfWindow }}</b></span>@endif
        @if ($report->excludedWrongDate)<span class="badge badge-warn">jiný den <b>{{ $report->excludedWrongDate }}</b></span>@endif
        @if ($report->ownSquareCount)<span class="badge badge-ok">vlastní čtverec (2 b.) <b>{{ $report->ownSquareCount }}</b></span>@endif
        @if ($report->excludedEmpty)<span class="badge badge-brand">bez lokátoru <b>{{ $report->excludedEmpty }}</b></span>@endif
        @if (count($report->ignoredLines))<span class="badge badge-danger">ignorováno <b>{{ count($report->ignoredLines) }}</b></span>@endif
        @if ($report->duplicateCount)<span class="badge badge-danger">duplikátů <b>{{ $report->duplicateCount }}</b></span>@endif
    </section>

    {{-- Ignorované řádky (neprošly parserem) --}}
    @if (count($report->ignoredLines))
        <details class="alert mb-5" open style="background-color:var(--warn-soft);border-color:color-mix(in oklab, var(--warn) 45%, transparent);color:var(--warn);">
            <summary class="cursor-pointer font-bold">Ignorované řádky ({{ count($report->ignoredLines) }}) – neprošly parserem, do bodů se nezapočítávají</summary>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($report->ignoredLines as $line)
                    <li><code class="break-all">{{ $line }}</code></li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Legenda --}}
    <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-muted">
        <span class="inline-flex items-center gap-1"><i class="inline-block h-3 w-3 rounded-sm" style="background:var(--ok)"></i> započteno</span>
        <span class="inline-flex items-center gap-1"><i class="inline-block h-3 w-3 rounded-sm" style="background:var(--warn)"></i> mimo okno / jiný den</span>
        <span class="inline-flex items-center gap-1"><i class="inline-block h-3 w-3 rounded-sm" style="background:var(--muted)"></i> bez lokátoru</span>
        <span class="badge badge-brand">vlastní čtverec</span>
        <span class="badge badge-brand">★ nový násobič</span>
        <span class="badge badge-danger">duplikát</span>
    </div>

    {{-- Rozpad QSO --}}
    <div class="table-wrap">
        <table class="data-table edx-qso">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Datum</th>
                    <th>Čas</th>
                    <th>Stanice</th>
                    <th>Přijatý WWL</th>
                    <th>Čtverec</th>
                    <th class="num">Body</th>
                    <th>Stav</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report->rows as $row)
                    @php
                        $cls = $row->counted
                            ? 'r-ok'
                            : (in_array($row->reason, ['out_of_window', 'wrong_date'], true) ? 'r-warn' : 'r-skip');
                    @endphp
                    <tr class="{{ $cls }}">
                        <td class="num text-muted">{{ $row->index }}</td>
                        <td class="num">{{ $fmtDate($row->date) }}</td>
                        <td class="num">{{ $fmtTime($row->time) }}</td>
                        <td class="font-bold">{{ $row->callSign }}</td>
                        <td>{{ $row->receivedWwl !== '' ? $row->receivedWwl : '—' }}</td>
                        <td>{{ $row->bigSquare !== '' ? $row->bigSquare : '—' }}</td>
                        <td class="num">{{ $row->counted ? $row->points : '—' }}</td>
                        <td class="whitespace-nowrap">
                            @switch($row->reason)
                                @case('counted')<span class="badge badge-ok">✓ započteno</span>@break
                                @case('out_of_window')<span class="badge badge-warn">mimo okno</span>@break
                                @case('wrong_date')<span class="badge badge-warn">jiný den</span>@break
                                @default<span class="badge badge-brand">bez lokátoru</span>
                            @endswitch
                            @if ($row->isOwnSquare && $row->counted)<span class="badge badge-brand ml-1">vlastní čtverec</span>@endif
                            @if ($row->newMultiplier)<span class="badge badge-brand ml-1">★ nový násobič</span>@endif
                            @if ($row->duplicate)<span class="badge badge-danger ml-1">duplikát</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted italic">Deník neobsahuje žádné naparsovatelné QSO.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
