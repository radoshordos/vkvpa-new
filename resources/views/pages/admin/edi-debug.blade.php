@extends('layouts.app')
@section('title', __('admin.debug_title'))

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

<h1>{{ __('admin.debug_heading') }}</h1>
<p class="max-w-prose text-sm text-muted">
    {{ __('admin.debug_desc') }}
</p>

@if ($errors->any())
    <x-alert type="error" class="mt-3">
        <strong>{{ __('admin.debug_err_title') }}</strong>
        <p class="mt-1">{{ $errors->first('upload') }}</p>
        @if (session('lineErrors'))
            <ul class="mt-2 list-disc pl-5">
                @foreach (session('lineErrors') as $le)
                    <li><code>{{ $le }}</code></li>
                @endforeach
            </ul>
        @endif
    </x-alert>
@endif

<form class="card mb-5 mt-4 flex flex-wrap items-end gap-4 p-4" action="{{ route('edi.debug.store') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="field mb-0">
        <span class="label">{{ __('admin.debug_file_label') }}</span>
        <input type="file" name="upload" accept=".edi,.txt" class="text-sm" required>
        <p class="mt-1 text-xs text-muted">{{ __('admin.debug_file_hint') }}</p>
    </div>
    <button type="submit" class="btn btn-primary">{{ __('admin.debug_btn') }}</button>
</form>

@if ($report)
    {{-- Hlavička deníku --}}
    <section class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @php
            $categoryLabel = match(true) {
                $report->categoryName !== null => $report->categoryName,
                $report->categoryId !== null   => __('admin.debug_cat_unknown', ['id' => $report->categoryId]),
                default                        => __('admin.debug_cat_none'),
            };
            $cards = [
                [__('admin.debug_card_call'), $report->call !== '' ? $report->call : '—', null],
                [__('admin.debug_card_loc'), $report->locator !== '' ? $report->locator : '—', __('admin.debug_card_square').' '.($report->homeSquare !== '' ? $report->homeSquare : '—')],
                [__('admin.debug_card_day'), $fmtDate($report->contestDay), null],
                [__('admin.debug_card_band'), $report->band !== '' ? $report->band : '—', $report->section !== '' ? $report->section : '—'],
                [__('admin.debug_card_cat').($report->categoryId !== null ? ' #'.$report->categoryId : ''), $categoryLabel, null],
                [__('admin.debug_card_power'), $report->power.' W', $report->qrp ? 'QRP' : null],
                [__('admin.debug_card_window'), $fmtTime($report->windowFrom).'–'.$fmtTime($report->windowTo), null],
            ];
        @endphp
        @foreach ($cards as [$k, $v, $sub])
            <div class="card p-3">
                <span class="block text-xs uppercase tracking-wide text-muted">{{ $k }}</span>
                <span class="block text-base font-bold text-ink">{{ $v }}@if ($sub)<small class="ml-1 font-normal text-muted">{{ $sub }}</small>@endif</span>
            </div>
        @endforeach
        @if ($edihead)
            <div class="card p-3">
                <span class="block text-xs uppercase tracking-wide text-muted">{{ __('admin.debug_card_maps') }}</span>
                <div class="mt-1 flex flex-wrap gap-1">
                    <x-vizualizace-odkaz :head="$edihead" target="_blank" />
                </div>
            </div>
        @endif
    </section>

    {{-- Skóre --}}
    <section class="mb-5 rounded-xl bg-brand p-5 text-brand-fg">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex flex-col leading-none">
                <b class="text-3xl">{{ $report->boduZaQso }}</b>
                <span class="mt-1 text-xs uppercase tracking-wide opacity-80">{{ __('admin.debug_score_pts') }}</span>
            </div>
            <span class="text-xl opacity-60">×</span>
            <div class="flex flex-col leading-none">
                <b class="text-3xl">{{ $report->nasobice }}</b>
                <span class="mt-1 text-xs uppercase tracking-wide opacity-80">{{ __('admin.debug_score_mult') }}</span>
            </div>
            <span class="text-xl opacity-60">=</span>
            <div class="flex flex-col leading-none">
                <b class="text-4xl text-amber-300">{{ $report->body }}</b>
                <span class="mt-1 text-xs uppercase tracking-wide opacity-80">{{ __('admin.debug_score_total') }}</span>
            </div>
        </div>
        <p class="mt-3 text-xs opacity-80">
            {{ __('admin.debug_score_desc', ['count' => $report->pocet, 'mult' => $report->nasobice - 1]) }}
        </p>
    </section>

    {{-- Souhrn parsování a vyloučení --}}
    <section class="mb-5 flex flex-wrap gap-2">
        <x-badge variant="brand">{{ __('admin.debug_badge_decl') }} <b>{{ $report->declaredTotal }}</b></x-badge>
        <x-badge variant="brand">{{ __('admin.debug_badge_parsed') }} <b>{{ $report->parsedCount }}</b></x-badge>
        <x-badge variant="ok">{{ __('admin.debug_badge_counted') }} <b>{{ $report->pocet }}</b></x-badge>
        @if ($report->excludedOutOfWindow)<x-badge variant="warn">{{ __('admin.debug_badge_window') }} <b>{{ $report->excludedOutOfWindow }}</b></x-badge>@endif
        @if ($report->excludedWrongDate)<x-badge variant="warn">{{ __('admin.debug_badge_date') }} <b>{{ $report->excludedWrongDate }}</b></x-badge>@endif
        @if ($report->ownSquareCount)<x-badge variant="ok">{{ __('admin.debug_badge_own') }} <b>{{ $report->ownSquareCount }}</b></x-badge>@endif
        @if ($report->excludedEmpty)<x-badge variant="brand">{{ __('admin.debug_badge_empty') }} <b>{{ $report->excludedEmpty }}</b></x-badge>@endif
        @if (count($report->ignoredLines))<x-badge variant="danger">{{ __('admin.debug_badge_ignored') }} <b>{{ count($report->ignoredLines) }}</b></x-badge>@endif
        @if ($report->duplicateCount)<x-badge variant="danger">{{ __('admin.debug_badge_dup') }} <b>{{ $report->duplicateCount }}</b></x-badge>@endif
    </section>

    {{-- Ignorované řádky (neprošly parserem) --}}
    @if (count($report->ignoredLines))
        <details class="alert mb-5" open style="background-color:var(--warn-soft);border-color:color-mix(in oklab, var(--warn) 45%, transparent);color:var(--warn);">
            <summary class="cursor-pointer font-bold">{{ __('admin.debug_ignored_title', ['count' => count($report->ignoredLines)]) }}</summary>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($report->ignoredLines as $line)
                    <li><code class="break-all">{{ $line }}</code></li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Legenda --}}
    <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-muted">
        <span class="inline-flex items-center gap-1"><i class="inline-block h-3 w-3 rounded-sm" style="background:var(--ok)"></i> {{ __('admin.debug_legend_ok') }}</span>
        <span class="inline-flex items-center gap-1"><i class="inline-block h-3 w-3 rounded-sm" style="background:var(--warn)"></i> {{ __('admin.debug_legend_warn') }}</span>
        <span class="inline-flex items-center gap-1"><i class="inline-block h-3 w-3 rounded-sm" style="background:var(--muted)"></i> {{ __('admin.debug_legend_skip') }}</span>
        <x-badge variant="brand">{{ __('admin.debug_legend_own') }}</x-badge>
        <x-badge variant="brand">{{ __('admin.debug_legend_new') }}</x-badge>
        <x-badge variant="danger">{{ __('admin.debug_legend_dup') }}</x-badge>
    </div>

    {{-- Rozpad QSO --}}
    <div class="table-wrap">
        <table class="data-table edx-qso">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('admin.debug_col_date') }}</th>
                    <th>{{ __('admin.debug_col_time') }}</th>
                    <th>{{ __('admin.debug_col_station') }}</th>
                    <th>{{ __('admin.debug_col_wwl') }}</th>
                    <th>{{ __('admin.debug_col_square') }}</th>
                    <th class="num">{{ __('admin.debug_col_pts') }}</th>
                    <th>{{ __('admin.debug_col_status') }}</th>
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
                        <td class="mono font-bold">{{ $row->callSign }}</td>
                        <td class="mono">{{ $row->receivedWwl !== '' ? $row->receivedWwl : '—' }}</td>
                        <td class="mono">{{ $row->bigSquare !== '' ? $row->bigSquare : '—' }}</td>
                        <td class="num">{{ $row->counted ? $row->points : '—' }}</td>
                        <td class="whitespace-nowrap">
                            @switch($row->reason)
                                @case('counted')<x-badge variant="ok">{{ __('admin.debug_status_ok') }}</x-badge>@break
                                @case('out_of_window')<x-badge variant="warn">{{ __('admin.debug_status_window') }}</x-badge>@break
                                @case('wrong_date')<x-badge variant="warn">{{ __('admin.debug_status_date') }}</x-badge>@break
                                @default<x-badge variant="brand">{{ __('admin.debug_status_empty') }}</x-badge>
                            @endswitch
                            @if ($row->isOwnSquare && $row->counted)<x-badge variant="brand" class="ml-1">{{ __('admin.debug_tag_own') }}</x-badge>@endif
                            @if ($row->newMultiplier)<x-badge variant="brand" class="ml-1">{{ __('admin.debug_tag_new_mult') }}</x-badge>@endif
                            @if ($row->duplicate)<x-badge variant="danger" class="ml-1">{{ __('admin.debug_tag_dup') }}</x-badge>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="italic text-muted">{{ __('admin.debug_empty_log') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif
@endsection
