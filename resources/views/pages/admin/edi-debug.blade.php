@extends('layouts.app')
@section('title', 'EDI debug – VKV PA')

@push('head')
<style>
    /* ── EDI debug – samostatný, izolovaný vzhled (prefix .edx) ─────────── */
    .edx { color: #1f2733; line-height: 1.45; }
    .edx * { box-sizing: border-box; }
    .edx__title { font-size: 22px; margin: 0 0 4px; color: #2a2360; }
    .edx__lead { margin: 0 0 18px; color: #5b6470; font-size: 13px; max-width: 60ch; }

    /* Upload panel */
    .edx-uploader {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px;
        background: linear-gradient(135deg, #f4f3fe 0%, #eef1f7 100%);
        border: 1px solid #ddd9f3; border-radius: 12px; padding: 16px 18px; margin-bottom: 20px;
    }
    .edx-uploader__field { display: flex; flex-direction: column; gap: 6px; }
    .edx-uploader__label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6a6480; }
    .edx-uploader input[type=file] { font-size: 13px; }
    .edx-uploader__hint { font-size: 12px; color: #8a8597; margin: 2px 0 0; }
    .edx-btn {
        appearance: none; border: 0; cursor: pointer; font: inherit; font-weight: 700;
        background: #4f46b8; color: #fff; padding: 9px 20px; border-radius: 8px;
        transition: background .15s ease;
    }
    .edx-btn:hover { background: #3c3596; color: #fff; }

    /* Alert */
    .edx-alert { border-radius: 10px; padding: 12px 16px; margin-bottom: 18px; font-size: 13px; }
    .edx-alert--error { background: #fdeceb; border: 1px solid #f4c4bf; color: #8d231a; }
    .edx-alert p { margin: 4px 0 0; }
    .edx-alert__list { margin: 8px 0 0; padding-left: 18px; }
    .edx-alert__list code { background: #fff; padding: 1px 5px; border-radius: 4px; }

    /* Karty hlavičky */
    .edx-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-bottom: 18px; }
    .edx-card { background: #fff; border: 1px solid #e4e7ee; border-radius: 10px; padding: 10px 12px; }
    .edx-card__k { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #8a909c; margin-bottom: 3px; }
    .edx-card__v { display: block; font-size: 15px; font-weight: 700; color: #28303d; }
    .edx-card__v small { font-weight: 400; color: #8a909c; font-size: 12px; }

    /* Skóre headline */
    .edx-score {
        background: radial-gradient(120% 140% at 0% 0%, #2a2360 0%, #4f46b8 100%);
        color: #fff; border-radius: 14px; padding: 18px 22px; margin-bottom: 18px;
    }
    .edx-score__formula { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .edx-score__factor { display: flex; flex-direction: column; line-height: 1.1; }
    .edx-score__factor b { font-size: 30px; }
    .edx-score__factor span { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; opacity: .8; margin-top: 2px; }
    .edx-score__factor--total b { font-size: 38px; color: #ffe27a; }
    .edx-score__op { font-size: 22px; opacity: .55; }
    .edx-score__note { margin: 10px 0 0; font-size: 12px; opacity: .8; }

    /* Souhrn parsování + důvody vyloučení */
    .edx-stats { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
    .edx-stat { display: inline-flex; align-items: baseline; gap: 6px; font-size: 12px; padding: 6px 12px; border-radius: 20px; background: #eef1f4; color: #475063; border: 1px solid #e0e4ea; }
    .edx-stat b { font-size: 14px; font-weight: 800; }
    .edx-stat--ok { background: #e7f6ec; color: #1c7c3d; border-color: #bfe6cb; }
    .edx-stat--warn { background: #fff6e0; color: #8a5d00; border-color: #f1d68f; }
    .edx-stat--bad { background: #fde9e7; color: #a5281c; border-color: #f3c2bc; }

    /* Ignorované řádky */
    .edx-ignored { background: #fff8ec; border: 1px solid #f1d68f; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px; font-size: 12px; }
    .edx-ignored > summary { cursor: pointer; font-weight: 700; color: #8a5d00; }
    .edx-ignored ul { margin: 8px 0 0; padding-left: 18px; }
    .edx-ignored code { background: #fff; padding: 1px 5px; border-radius: 4px; word-break: break-all; }

    /* Legenda */
    .edx-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; font-size: 11px; color: #6a7180; }
    .edx-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .edx-legend i { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
    .edx-legend .sw-ok { background: #2ea35a; }
    .edx-legend .sw-warn { background: #e8a517; }
    .edx-legend .sw-skip { background: #aab2bf; }

    /* Tabulka QSO */
    .edx-tablewrap { overflow-x: auto; border: 1px solid #e4e7ee; border-radius: 10px; }
    table.edx-qso { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    table.edx-qso thead th {
        position: sticky; top: 0; background: #f3f4f9; color: #4a5161; text-align: left;
        font-size: 11px; text-transform: uppercase; letter-spacing: .03em; padding: 9px 10px;
        border-bottom: 2px solid #e1e4ec; white-space: nowrap;
    }
    table.edx-qso td { padding: 7px 10px; border-bottom: 1px solid #eef0f4; vertical-align: middle; }
    table.edx-qso tbody tr:last-child td { border-bottom: 0; }
    table.edx-qso .num { font-variant-numeric: tabular-nums; color: #4a5161; }
    table.edx-qso .call { font-weight: 700; }
    table.edx-qso .sq { font-variant-numeric: tabular-nums; letter-spacing: .02em; }
    table.edx-qso .idx { color: #aab0bd; font-variant-numeric: tabular-nums; }

    /* Stavy řádků – levý barevný proužek + jemné pozadí */
    .edx-qso tr.r-ok td:first-child { box-shadow: inset 3px 0 0 #2ea35a; }
    .edx-qso tr.r-warn td:first-child { box-shadow: inset 3px 0 0 #e8a517; }
    .edx-qso tr.r-skip td:first-child { box-shadow: inset 3px 0 0 #aab2bf; }
    .edx-qso tr.r-ok { background: #fbfefc; }
    .edx-qso tr.r-warn { background: #fffdf6; }
    .edx-qso tr.r-skip { background: #fafbfc; color: #7a818d; }
    .edx-qso tr:hover { background: #f5f7ff; }

    /* Odznaky stavu */
    .b { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    .b--ok { background: #e3f5ea; color: #1c7c3d; }
    .b--warn { background: #fdf0d6; color: #8a5d00; }
    .b--skip { background: #eceef2; color: #69707d; }
    .b--mult { background: #ece6fb; color: #5b3fa6; margin-left: 4px; }
    .b--dup { background: #fde7e5; color: #a5281c; margin-left: 4px; }

    .edx-empty { color: #8a909c; font-style: italic; padding: 14px 0; }
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
<div class="edx">
    <h1 class="edx__title">EDI debug – kontrola bodování</h1>
    <p class="edx__lead">
        Nahraj EDI deník a zkontroluj řádek po řádku, jak vzniká skóre – které QSO se započítává,
        které spadá mimo závodní okno či den a které přináší nový násobič. Slouží jen pro náhled,
        do databáze se nic neukládá.
    </p>

    @if ($errors->any())
        <div class="edx-alert edx-alert--error">
            <strong>Soubor se nepodařilo zpracovat.</strong>
            <p>{{ $errors->first('upload') }}</p>
            @if (session('lineErrors'))
                <ul class="edx-alert__list">
                    @foreach (session('lineErrors') as $le)
                        <li><code>{{ $le }}</code></li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <form class="edx-uploader" action="{{ route('edi.debug.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <div class="edx-uploader__field">
            <span class="edx-uploader__label">EDI soubor</span>
            <input type="file" name="upload" accept=".edi,.txt" required>
            <p class="edx-uploader__hint">Max 500 kB. Analyzuje se lokálně, nic se neukládá.</p>
        </div>
        <button type="submit" class="edx-btn">Analyzovat deník</button>
    </form>

    @if ($report)
        {{-- Hlavička deníku --}}
        <section class="edx-cards">
            <div class="edx-card">
                <span class="edx-card__k">Značka</span>
                <span class="edx-card__v">{{ $report->call !== '' ? $report->call : '—' }}</span>
            </div>
            <div class="edx-card">
                <span class="edx-card__k">Lokátor</span>
                <span class="edx-card__v">
                    {{ $report->locator !== '' ? $report->locator : '—' }}
                    <small>čtverec {{ $report->homeSquare !== '' ? $report->homeSquare : '—' }}</small>
                </span>
            </div>
            <div class="edx-card">
                <span class="edx-card__k">Den závodu</span>
                <span class="edx-card__v">{{ $fmtDate($report->contestDay) }}</span>
            </div>
            <div class="edx-card">
                <span class="edx-card__k">Pásmo / sekce</span>
                <span class="edx-card__v">
                    {{ $report->band !== '' ? $report->band : '—' }}
                    <small>{{ $report->section !== '' ? $report->section : '—' }}</small>
                </span>
            </div>
            <div class="edx-card">
                <span class="edx-card__k">Výkon</span>
                <span class="edx-card__v">
                    {{ $report->power }} W
                    @if ($report->qrp)<small style="color:#1c7c3d;font-weight:700">QRP</small>@endif
                </span>
            </div>
            <div class="edx-card">
                <span class="edx-card__k">Závodní okno (UTC)</span>
                <span class="edx-card__v">{{ $fmtTime($report->windowFrom) }}–{{ $fmtTime($report->windowTo) }}</span>
            </div>
        </section>

        {{-- Skóre --}}
        <section class="edx-score">
            <div class="edx-score__formula">
                <div class="edx-score__factor"><b>{{ $report->pocet }}</b><span>počet QSO</span></div>
                <span class="edx-score__op">×</span>
                <div class="edx-score__factor"><b>{{ $report->nasobice }}</b><span>násobič</span></div>
                <span class="edx-score__op">=</span>
                <div class="edx-score__factor edx-score__factor--total"><b>{{ $report->body }}</b><span>bodů</span></div>
            </div>
            <p class="edx-score__note">
                Násobič = {{ $report->nasobice - 1 }} různých cizích velkých čtverců + 1 domácí.
            </p>
        </section>

        {{-- Souhrn parsování a vyloučení --}}
        <section class="edx-stats">
            <span class="edx-stat">deklarováno&nbsp;<b>{{ $report->declaredTotal }}</b></span>
            <span class="edx-stat">naparsováno&nbsp;<b>{{ $report->parsedCount }}</b></span>
            <span class="edx-stat edx-stat--ok">započteno&nbsp;<b>{{ $report->pocet }}</b></span>
            @if ($report->excludedOutOfWindow)
                <span class="edx-stat edx-stat--warn">mimo okno&nbsp;<b>{{ $report->excludedOutOfWindow }}</b></span>
            @endif
            @if ($report->excludedWrongDate)
                <span class="edx-stat edx-stat--warn">jiný den&nbsp;<b>{{ $report->excludedWrongDate }}</b></span>
            @endif
            @if ($report->excludedOwnSquare)
                <span class="edx-stat">vlastní čtverec&nbsp;<b>{{ $report->excludedOwnSquare }}</b></span>
            @endif
            @if ($report->excludedEmpty)
                <span class="edx-stat">bez lokátoru&nbsp;<b>{{ $report->excludedEmpty }}</b></span>
            @endif
            @if (count($report->ignoredLines))
                <span class="edx-stat edx-stat--bad">ignorováno&nbsp;<b>{{ count($report->ignoredLines) }}</b></span>
            @endif
            @if ($report->duplicateCount)
                <span class="edx-stat edx-stat--bad">duplikátů&nbsp;<b>{{ $report->duplicateCount }}</b></span>
            @endif
        </section>

        {{-- Ignorované řádky (neprošly parserem) --}}
        @if (count($report->ignoredLines))
            <details class="edx-ignored" open>
                <summary>Ignorované řádky ({{ count($report->ignoredLines) }}) – neprošly parserem, do bodů se nezapočítávají</summary>
                <ul>
                    @foreach ($report->ignoredLines as $line)
                        <li><code>{{ $line }}</code></li>
                    @endforeach
                </ul>
            </details>
        @endif

        {{-- Legenda --}}
        <div class="edx-legend">
            <span><i class="sw-ok"></i> započteno</span>
            <span><i class="sw-warn"></i> mimo okno / jiný den</span>
            <span><i class="sw-skip"></i> vlastní čtverec / bez lokátoru</span>
            <span><span class="b b--mult">★ nový násobič</span></span>
            <span><span class="b b--dup">duplikát</span></span>
        </div>

        {{-- Rozpad QSO --}}
        <div class="edx-tablewrap">
            <table class="edx-qso">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Datum</th>
                        <th>Čas</th>
                        <th>Stanice</th>
                        <th>Přijatý WWL</th>
                        <th>Čtverec</th>
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
                            <td class="idx">{{ $row->index }}</td>
                            <td class="num">{{ $fmtDate($row->date) }}</td>
                            <td class="num">{{ $fmtTime($row->time) }}</td>
                            <td class="call">{{ $row->callSign }}</td>
                            <td class="sq">{{ $row->receivedWwl !== '' ? $row->receivedWwl : '—' }}</td>
                            <td class="sq">{{ $row->bigSquare !== '' ? $row->bigSquare : '—' }}</td>
                            <td>
                                @switch($row->reason)
                                    @case('counted')
                                        <span class="b b--ok">✓ započteno</span>
                                        @break
                                    @case('out_of_window')
                                        <span class="b b--warn">mimo okno</span>
                                        @break
                                    @case('wrong_date')
                                        <span class="b b--warn">jiný den</span>
                                        @break
                                    @case('own_square')
                                        <span class="b b--skip">vlastní čtverec</span>
                                        @break
                                    @default
                                        <span class="b b--skip">bez lokátoru</span>
                                @endswitch
                                @if ($row->newMultiplier)<span class="b b--mult">★ nový násobič</span>@endif
                                @if ($row->duplicate)<span class="b b--dup">duplikát</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="edx-empty">Deník neobsahuje žádné naparsovatelné QSO.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
