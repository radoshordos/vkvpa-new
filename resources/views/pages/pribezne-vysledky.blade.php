{{--
    Průběžné výsledky kola – všechna převzatá hlášení seřazená dle bodů.
--}}
@extends('layouts.app')
@section('title', __('pages.pribezne.title'))

@section('content')

<h1>{{ __('pages.pribezne.heading') }}</h1>

<div class="alert alert-info mb-4 text-sm">{{ __('pages.pribezne.notice') }}</div>

<form method="get" action="{{ route('pribezne_vysledky') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
    <div class="field mb-0">
        <label class="label" for="kolo">{{ __('pages.pribezne.filter_round') }}</label>
        <select id="kolo" name="kolo" class="select w-auto">
            @foreach ($kola as $k)
                <option value="{{ $k->id }}" @selected($kolo && $k->id === $kolo->id)>{{ $k->nazev }} ({{ $k->datum_konani?->format('j.n.Y') }})</option>
            @endforeach
        </select>
    </div>
    <div class="field mb-0">
        <label class="label" for="kategorie">{{ __('pages.pribezne.filter_category') }}</label>
        <select id="kategorie" name="kategorie" class="select w-auto">
            <option value="0" @selected($katId === 0)>{{ __('pages.pribezne.filter_all') }}</option>
            @foreach ($kategorie as $kat)
                <option value="{{ $kat->id }}" @selected($katId === $kat->id)>{{ $kat->nazev }}</option>
            @endforeach
        </select>
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm">
        <input id="qrp" type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp'))> {{ __('pages.pribezne.filter_qrp') }}
    </label>
    <button type="submit" class="btn btn-primary">{{ __('pages.pribezne.btn_show') }}</button>
</form>

@if (! $kolo)
    <p class="text-muted">{{ __('pages.pribezne.no_round') }}</p>
@else
    @if ($kolo->vyhodnoceno)
        <p class="mb-3 text-sm text-muted">{{ __('pages.pribezne.evaluated_on', ['date' => $kolo->vyhodnoceno->format('j.n.Y')]) }}</p>
    @else
        <p class="mb-3 text-sm text-warn">{{ __('pages.pribezne.not_evaluated') }}</p>
    @endif

    @if ($radky->isEmpty())
        <p class="text-muted">{{ __('pages.pribezne.no_results') }}</p>
    @else
        @foreach ($radky->groupBy('id_kategorie') as $katId => $skupina)
            <div class="section-head">{{ $kategorie[$katId]->nazev ?? ('Kategorie ' . $katId) }}</div>
            <div class="table-wrap mb-4">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="num">{{ __('pages.pribezne.col_pos') }}</th>
                            <th>{{ __('pages.pribezne.col_callsign') }}</th>
                            <th>{{ __('pages.pribezne.col_locator') }}</th>
                            <th class="num">{{ __('pages.pribezne.col_qso') }}</th>
                            <th class="num">{{ __('pages.pribezne.col_mult') }}</th>
                            <th class="num">{{ __('pages.pribezne.col_total') }}</th>
                            <th>{{ __('pages.pribezne.col_soapbox') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $rank = 0; $prevBody = null; @endphp
                    @foreach ($skupina as $r)
                        @php
                            if ($r->body !== $prevBody) { $rank++; $prevBody = $r->body; }
                        @endphp
                        <tr>
                            <td class="num font-bold">{{ $rank }}.</td>
                            <td>
                                <span class="mono font-bold">{{ $r->znacka }}</span>@if ($r->qrp)<span class="badge badge-qrp ml-1">QRP</span>@endif
                                @if ($r->jmeno)<br><span class="text-muted">{{ $r->jmeno }}</span>@endif
                            </td>
                            <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                            <td class="num">{{ $r->pocet }}</td>
                            <td class="num">{{ $r->nasobice }}</td>
                            <td class="num font-bold text-warn">{{ $r->body }}</td>
                            <td class="text-danger">{{ $r->soapbox }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
@endif

@push('scripts')
<script>
(function () {
    var form = document.getElementById('kolo')?.closest('form');
    if (form) {
        document.getElementById('kolo').addEventListener('change', function () { form.submit(); });
        document.getElementById('kategorie').addEventListener('change', function () { form.submit(); });
        document.getElementById('qrp').addEventListener('change', function () { form.submit(); });
    }
}());
</script>
@endpush
@endsection
