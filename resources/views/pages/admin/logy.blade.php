@extends('layouts.app')
@section('title', __('admin.logy_title'))
@section('content')

<h1>{{ __('admin.logy_heading') }}</h1>

<p class="mb-4 max-w-prose text-sm text-muted">{{ __('admin.logy_desc') }}</p>

@if (empty($files))
    <p class="text-muted">{{ __('admin.logy_empty') }}</p>
@else
    {{-- Výběr log souboru (GET, žádný JS – kompatibilní s CSP). --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-sm text-muted">{{ __('admin.logy_file') }}:</span>
        @foreach ($files as $f)
            <a href="{{ route('logy.index', ['soubor' => $f]) }}"
               @class([
                   'rounded-lg border px-3 py-1 text-sm mono',
                   'border-accent bg-accent/10 text-heading font-semibold' => $f === $current,
                   'border-line text-muted hover:bg-surface-2' => $f !== $current,
               ])>{{ $f }}</a>
        @endforeach
    </div>

    @php
        $rows = $logs ?? [];
        $levelColors = [
            'emergency' => 'bg-red-100 text-red-800',
            'alert' => 'bg-red-100 text-red-800',
            'critical' => 'bg-red-100 text-red-800',
            'error' => 'bg-red-100 text-red-800',
            'warning' => 'bg-amber-100 text-amber-800',
            'notice' => 'bg-sky-100 text-sky-800',
            'info' => 'bg-sky-100 text-sky-800',
            'debug' => 'bg-slate-100 text-slate-700',
        ];
    @endphp

    @if ($logs === null)
        <p class="text-muted">{{ __('admin.logy_too_large') }}</p>
    @elseif (empty($rows))
        <p class="text-muted">{{ __('admin.logy_no_entries') }}</p>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.logy_col_level') }}</th>
                        <th>{{ __('admin.logy_col_date') }}</th>
                        <th>{{ __('admin.logy_col_text') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $log)
                        <tr>
                            <td class="whitespace-nowrap align-top">
                                @if (! empty($log['level']))
                                    <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold uppercase {{ $levelColors[$log['level']] ?? 'bg-slate-100 text-slate-700' }}">{{ $log['level'] }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap align-top mono text-sm text-muted">{{ $log['date'] ?? '' }}</td>
                            <td class="align-top text-sm">
                                <span class="break-all">{{ $log['text'] ?? '' }}</span>
                                @if (! empty($log['stack']))
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs text-muted">{{ __('admin.logy_stack') }}</summary>
                                        <pre class="mt-1 max-w-full overflow-x-auto whitespace-pre-wrap break-all rounded bg-surface-2 p-2 text-xs">{{ trim($log['stack']) }}</pre>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@endsection
