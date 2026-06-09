@extends('layouts.app')
@section('title', __('admin.importy_title'))
@section('content')

<h1>{{ __('admin.importy_heading') }}</h1>

<p class="mb-4 text-sm text-muted">
    {!! __('admin.importy_desc') !!}
</p>

<x-form-errors />

{{-- Stejný design jako panel „Načíst EDI soubor" na /hlaseni --}}
<div class="card mb-8">
    <div class="flex items-center gap-3 border-b border-line px-5 py-4">
        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-soft">
            <x-icon name="file" class="h-5 w-5 text-brand" />
        </div>
        <p class="text-sm font-semibold text-heading">{{ __('admin.importy_heading') }}</p>
    </div>

    <div class="px-5 py-4">
        <form method="post" action="{{ route('importy.store') }}" enctype="multipart/form-data">
            @csrf
            <label class="upload-zone" id="zip-zone">
                <input
                    type="file" name="zip" id="zip-file" accept=".zip,application/zip" required class="sr-only"
                    onchange="var z=document.getElementById('zip-zone'),n=document.getElementById('zip-name');z.classList.toggle('has-file',!!this.files[0]);n.textContent=this.files[0]?this.files[0].name:''"
                >
                <svg class="upload-zone-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                </svg>
                <span id="zip-name" class="upload-zone-name">{{ __('admin.importy_zip_label') }}…</span>
                <span class="upload-zone-hint">{{ __('admin.importy_zip_hint') }}</span>
            </label>

            <div class="mt-3 flex items-center gap-3">
                <button type="submit" class="btn btn-primary">{{ __('admin.importy_btn') }}</button>
            </div>
        </form>
    </div>
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
                        <th class="min-w-[16rem]">{{ __('admin.importy_col_note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results['items'] as $item)
                        <tr>
                            <td class="mono wrap-anywhere text-sm">{{ $item['file'] }}</td>
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

@push('scripts')
<script>
(function () {
    var zone  = document.getElementById('zip-zone');
    var input = document.getElementById('zip-file');
    if (! zone || ! input) return;

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', function (e) {
        if (! zone.contains(e.relatedTarget)) zone.classList.remove('dragover');
    });

    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });

    // Drop mimo zónu nesmí otevřít soubor místo stránky
    document.addEventListener('dragover', function (e) { e.preventDefault(); });
    document.addEventListener('drop', function (e) { e.preventDefault(); });
}());
</script>
@endpush
@endsection
