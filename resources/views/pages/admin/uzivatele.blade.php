@extends('layouts.app')
@section('title', __('admin.uzivatele_title'))
@section('content')

<h1>{{ __('admin.uzivatele_heading') }}</h1>

<p class="mb-4 max-w-prose text-sm text-muted">{{ __('admin.uzivatele_desc') }}</p>

<form method="get" action="{{ route('uzivatele.index') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
    <div class="field mb-0">
        <label class="label" for="kolo">{{ __('admin.uzivatele_filter_round') }}</label>
        <select id="kolo" name="kolo" class="select w-auto">
            <option value="0">{{ __('admin.uzivatele_filter_all_rounds') }}</option>
            @foreach ($kola as $id => $nazev)
                <option value="{{ $id }}" @selected($koloId === $id)>{{ $nazev }}</option>
            @endforeach
        </select>
    </div>
    <div class="field mb-0">
        <label class="label" for="q">{{ __('admin.uzivatele_filter_search') }}</label>
        <input id="q" type="text" name="q" value="{{ $q }}" placeholder="{{ __('admin.uzivatele_filter_search_ph') }}" class="input w-64">
    </div>
    <button type="submit" class="btn btn-primary">{{ __('admin.uzivatele_btn_show') }}</button>
</form>

@if ($zaznamy->isEmpty())
    <p class="text-muted">{{ __('admin.uzivatele_empty') }}</p>
@else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('admin.uzivatele_col_call') }}</th>
                    <th>{{ __('admin.uzivatele_col_name') }}</th>
                    <th>{{ __('admin.uzivatele_col_email') }}</th>
                    <th>{{ __('admin.uzivatele_col_phone') }}</th>
                    <th>{{ __('admin.uzivatele_col_round') }}</th>
                    <th class="hidden @[520px]:table-cell">{{ __('admin.uzivatele_col_sent') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($zaznamy as $z)
                    <tr>
                        <td class="mono font-bold">{{ $z->callsign ?: '—' }}</td>
                        <td>{{ $z->name ?: '—' }}</td>
                        <td class="text-sm">@if ($z->email)<a href="mailto:{{ $z->email }}" class="link">{{ $z->email }}</a>@else—@endif</td>
                        <td class="mono text-sm">@if ($z->phone)<a href="tel:{{ preg_replace('/\s+/', '', $z->phone) }}" class="link">{{ $z->phone }}</a>@else—@endif</td>
                        <td class="text-sm">{{ $kola->get($z->round_id, '—') }}</td>
                        <td class="hidden whitespace-nowrap text-sm text-muted @[520px]:table-cell">{{ $z->submitted_at?->format('j. n. Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $zaznamy->links() }}
    </div>
@endif

@endsection
