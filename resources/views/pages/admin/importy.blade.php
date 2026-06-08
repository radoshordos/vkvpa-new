@extends('layouts.app')
@section('title', __('admin.importy_title'))
@section('content')

<h1>{{ __('admin.importy_heading') }}</h1>

<p class="mb-4 max-w-prose text-sm text-muted">
    {!! __('admin.importy_desc') !!}
</p>

<x-form-errors />

<div class="card mb-8 max-w-xl p-5">
    <form method="post" action="{{ route('importy.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
        @csrf
        <x-field name="zip" id="zip" type="file" :label="__('admin.importy_zip_label')"
                 wrapper="mb-0 min-w-48 flex-1" accept=".zip,application/zip" required />
        <button type="submit" class="btn btn-primary">{{ __('admin.importy_btn') }}</button>
    </form>
</div>

@if ($results)
    @php
        $statusColor = $results['errors'] > 0 ? 'alert-error' : ($results['imported'] > 0 ? 'alert-success' : 'alert-info');
    @endphp

    <div class="alert {{ $statusColor }} mb-4 flex flex-wrap gap-4">
        <span><b>{{ $results['total'] }}</b> {{ __('admin.importy_processed') }}</span>
        <span class="font-bold text-ok">✓ {{ $results['imported'] }} {{ __('admin.importy_imported') }}</span>
        @if ($results['skipped'] > 0)
            <span class="text-muted">⟳ {{ $results['skipped'] }} {{ __('admin.importy_skipped') }}</span>
        @endif
        @if ($results['errors'] > 0)
            <span class="font-bold text-danger">✕ {{ $results['errors'] }} {{ __('admin.importy_errors') }}</span>
        @endif
    </div>

    @if (! empty($results['items']))
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.importy_col_file') }}</th>
                        <th>{{ __('admin.importy_col_status') }}</th>
                        <th>{{ __('admin.importy_col_call') }}</th>
                        <th>{{ __('admin.importy_col_round') }}</th>
                        <th class="num">{{ __('admin.importy_col_pts') }}</th>
                        <th>{{ __('admin.importy_col_note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results['items'] as $item)
                        <tr>
                            <td class="mono text-sm">{{ $item['file'] }}</td>
                            <td>
                                @switch($item['status'])
                                    @case('ok')
                                        <x-badge variant="ok">{{ __('admin.importy_status_ok') }}</x-badge>
                                        @break
                                    @case('skip')
                                        <x-badge variant="brand">{{ __('admin.importy_status_skip') }}</x-badge>
                                        @break
                                    @default
                                        <x-badge variant="danger">{{ __('admin.importy_status_err') }}</x-badge>
                                @endswitch
                            </td>
                            <td class="mono font-bold">{{ $item['znacka'] ?? '—' }}</td>
                            <td class="text-sm">{{ $item['kolo'] ?? '—' }}</td>
                            <td class="num">{{ isset($item['body']) ? $item['body'] : '—' }}</td>
                            <td class="text-sm text-muted">{{ $item['reason'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif

@endsection
