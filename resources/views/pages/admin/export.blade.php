@extends('layouts.app')
@section('title', __('admin.export_title'))
@section('content')

<h1>{{ __('admin.export_heading') }}</h1>

<p class="mb-4 max-w-prose text-sm text-muted">
    {{ __('admin.export_desc') }}
</p>

@if ($kola->isEmpty())
    <p class="text-muted">{{ __('admin.export_empty') }}</p>
@else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('admin.export_col_round') }}</th>
                    <th class="hidden whitespace-nowrap @[520px]:table-cell">{{ __('admin.export_col_date') }}</th>
                    <th class="num">{{ __('admin.export_col_count') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kola as $k)
                    <tr>
                        <td>{{ $k['nazev'] }}</td>
                        <td class="hidden whitespace-nowrap text-sm text-muted @[520px]:table-cell">
                            {{ $k['starts_at']?->format('j. n. Y') ?? '—' }}
                        </td>
                        <td class="num">{{ $k['pocet'] }}</td>
                        <td class="whitespace-nowrap text-sm">
                            @if ($k['pocet'] > 0)
                                <a href="{{ route('export.download', $k['id']) }}" class="link">{{ __('admin.export_btn_zip') }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
