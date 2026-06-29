@extends('layouts.app')
@section('title', __('admin.zaloha_title'))
@section('content')

<h1 class="max-w-6xl">{{ __('admin.zaloha_heading') }}</h1>

<p class="mb-5 max-w-3xl text-sm text-muted">
    {!! __('admin.zaloha_desc') !!}
</p>

@php
    $backupTables = \App\Support\VkvpaSettings::dbBackupTables();
    $selectedTables = collect(old('tables', $backupTables));
    $allTablesSelected = $backupTables !== []
        && collect($backupTables)->every(static fn (string $table): bool => $selectedTables->contains($table));
@endphp

<form method="POST" action="{{ route('zaloha.download') }}" class="max-w-6xl">
    @csrf

    @error('tables')
        <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div class="mb-4 flex flex-col gap-3 rounded-lg border border-line bg-surface p-4 sm:flex-row sm:items-center sm:justify-between">
        <label class="flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" data-check-all @checked($allTablesSelected)>
            {{ __('admin.zaloha_select_all') }}
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="gzip" value="1" @checked(old('gzip', true))>
            {{ __('admin.zaloha_gzip') }}
        </label>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($groups as $group => $rows)
            <fieldset class="rounded-lg border border-line bg-surface p-4">
                <legend class="px-1 text-sm font-semibold">{{ __('admin.zaloha_group_'.$group) }}</legend>

                @foreach ($rows as $row)
                    <label class="flex items-center justify-between gap-3 py-1.5">
                        <span class="flex min-w-0 items-center gap-2">
                            <input type="checkbox" name="tables[]" value="{{ $row['name'] }}"
                                   data-check-item
                                   @checked($selectedTables->contains($row['name']))>
                            <code class="truncate text-sm" title="{{ $row['name'] }}">{{ $row['name'] }}</code>
                        </span>
                        <span class="shrink-0 text-sm text-muted">{{ number_format($row['count'], 0, ',', ' ') }}&nbsp;{{ __('admin.zaloha_col_rows') }}</span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
    </div>

    <div class="mt-5 flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-start sm:justify-between">
        <button type="submit" class="btn btn-primary">{{ __('admin.zaloha_btn_download') }}</button>

        <div class="max-w-2xl text-xs text-muted sm:text-right">
            <p>{{ __('admin.zaloha_hint') }}</p>
            <p class="mt-2">{!! __('admin.zaloha_files_hint') !!}</p>
        </div>
    </div>
</form>

@endsection
