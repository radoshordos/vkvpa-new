@extends('layouts.app')
@section('title', __('admin.zaloha_title'))
@section('content')

<h1>{{ __('admin.zaloha_heading') }}</h1>

<p class="mb-4 max-w-prose text-sm text-muted">
    {!! __('admin.zaloha_desc') !!}
</p>

<form method="POST" action="{{ route('zaloha.download') }}" class="max-w-xl">
    @csrf

    @error('tables')
        <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <label class="mb-3 flex items-center gap-2 text-sm font-medium">
        <input type="checkbox" data-check-all checked>
        {{ __('admin.zaloha_select_all') }}
    </label>

    @foreach ($groups as $group => $rows)
        <fieldset class="mb-4 rounded-xl border border-line bg-surface p-4">
            <legend class="px-1 text-sm font-semibold">{{ __('admin.zaloha_group_'.$group) }}</legend>

            @foreach ($rows as $row)
                <label class="flex items-center justify-between gap-3 py-1">
                    <span class="flex items-center gap-2">
                        <input type="checkbox" name="tables[]" value="{{ $row['name'] }}"
                               data-check-item
                               @checked(collect(old('tables', \App\Support\VkvpaSettings::dbBackupTables()))->contains($row['name']))>
                        <code class="text-sm">{{ $row['name'] }}</code>
                    </span>
                    <span class="text-sm text-muted">{{ number_format($row['count'], 0, ',', ' ') }}&nbsp;{{ __('admin.zaloha_col_rows') }}</span>
                </label>
            @endforeach
        </fieldset>
    @endforeach

    <label class="mb-4 flex items-center gap-2 text-sm">
        <input type="checkbox" name="gzip" value="1" @checked(old('gzip', true))>
        {{ __('admin.zaloha_gzip') }}
    </label>

    <button type="submit" class="btn-primary">{{ __('admin.zaloha_btn_download') }}</button>

    <p class="mt-3 max-w-prose text-xs text-muted">{{ __('admin.zaloha_hint') }}</p>
</form>

@endsection
