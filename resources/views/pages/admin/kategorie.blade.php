@extends('layouts.app')
@section('title', __('admin.kategorie_title'))
@section('content')

<div class="flex items-center justify-between gap-3 max-w-3xl">
    <h1>{{ __('admin.kategorie_heading') }}</h1>
    <a href="{{ route('kategorie.create') }}" class="btn btn-primary">{{ __('admin.kategorie_add') }}</a>
</div>

{{-- announcement řeší centrální <x-flash /> v layoutu --}}

{{-- max-w-3xl: pouhých 6 úzkých sloupců – roztažení na celou šířku by sloupce nečitelně rozházelo --}}
<div class="table-wrap mb-8 max-w-3xl">
    <table class="data-table">
        <thead>
            <tr>
                <th class="num">ID</th>
                <th>{{ __('admin.kategorie_col_name') }}</th>
                <th>{{ __('admin.kategorie_col_abbr') }}</th>
                <th class="num">dxid</th>
                <th>{{ __('admin.kategorie_col_actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($kategorie as $k)
                <tr>
                    <td class="num text-muted">{{ $k->id }}</td>
                    <td class="font-bold">{{ $k->nazev }}</td>
                    <td class="mono">{{ $k->zkratka }}</td>
                    <td class="num">{{ $k->dxid }}</td>
                    <td>
                        <a href="{{ route('kategorie.edit', $k->id) }}" class="icon-btn icon-btn-u" title="{{ __('admin.kategorie_btn_edit') }}">
                            <x-icon name="pencil" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="italic text-muted">{{ __('admin.kategorie_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
