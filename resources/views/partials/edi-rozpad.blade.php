{{--
    Per-QSO rozpad bodování deníku ($report = App\Services\Scoring\EdiDebugReport):
    u každého spojení je vidět, zda se započítalo a proč (ne). Sdílí náhled
    podání hlášení (Livewire Prihlaska) i admin „EDI debug".
--}}
@php
    $fmtDate = static fn (string $d): string => strlen($d) === 6
        ? substr($d, 4, 2).'.'.substr($d, 2, 2).'.'.substr($d, 0, 2)
        : ($d !== '' ? $d : '—');
    $fmtTime = static fn (string $t): string => strlen($t) === 4
        ? substr($t, 0, 2).':'.substr($t, 2, 2)
        : ($t !== '' ? $t : '—');
@endphp

{{-- Souhrn parsování a vyloučení --}}
<div class="mb-3 flex flex-wrap gap-2">
    <x-badge variant="brand">{{ __('admin.debug_badge_decl') }} <b>{{ $report->declaredTotal }}</b></x-badge>
    <x-badge variant="brand">{{ __('admin.debug_badge_parsed') }} <b>{{ $report->parsedCount }}</b></x-badge>
    <x-badge variant="ok">{{ __('admin.debug_badge_counted') }} <b>{{ $report->pocet }}</b></x-badge>
    @if ($report->excludedIncomplete)<x-badge variant="danger">{{ __('admin.debug_badge_incomplete') }} <b>{{ $report->excludedIncomplete }}</b></x-badge>@endif
    @if ($report->excludedOutOfWindow)<x-badge variant="warn">{{ __('admin.debug_badge_window') }} <b>{{ $report->excludedOutOfWindow }}</b></x-badge>@endif
    @if ($report->excludedWrongDate)<x-badge variant="warn">{{ __('admin.debug_badge_date') }} <b>{{ $report->excludedWrongDate }}</b></x-badge>@endif
    @if ($report->excludedEmpty)<x-badge variant="brand">{{ __('admin.debug_badge_empty') }} <b>{{ $report->excludedEmpty }}</b></x-badge>@endif
    @if (count($report->ignoredLines))<x-badge variant="danger">{{ __('admin.debug_badge_ignored') }} <b>{{ count($report->ignoredLines) }}</b></x-badge>@endif
    @if ($report->duplicateCount)<x-badge variant="danger">{{ __('admin.debug_badge_dup') }} <b>{{ $report->duplicateCount }}</b></x-badge>@endif
</div>

{{-- Řádky, které neprošly parserem --}}
@if (count($report->ignoredLines))
    <div class="mb-3 text-sm text-warn">
        <p class="font-medium">{{ __('admin.debug_ignored_title', ['count' => count($report->ignoredLines)]) }}</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($report->ignoredLines as $line)
                <li><code class="break-all">{{ $line }}</code></li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Rozpad QSO řádek po řádku --}}
<div class="table-wrap">
    <table class="data-table">
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
                    $barva = $row->counted
                        ? 'var(--ok)'
                        : (in_array($row->reason, ['out_of_window', 'wrong_date'], true) ? 'var(--warn)' : 'var(--muted)');
                @endphp
                <tr style="box-shadow: inset 3px 0 0 {{ $barva }}" @class(['text-muted' => ! $row->counted])>
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
                            @case('incomplete_exchange')<x-badge variant="danger">{{ __('admin.debug_status_incomplete') }}</x-badge>@break
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
