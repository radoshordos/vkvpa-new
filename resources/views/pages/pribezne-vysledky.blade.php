{{--
    Průběžné výsledky kola – samostatná veřejná stránka.
    Tabulka je shodná se spodní částí stránky „Načíst EDI soubor"
    (včetně nepřevzatých hlášení = stav „Čeká"), navíc s filtry kola a kategorie.
--}}
@extends('layouts.app')
@section('title', __('pages.pribezne.title'))

@section('content')

<h1>{{ __('pages.pribezne.heading') }}</h1>

<form method="get" action="{{ route('pribezne_vysledky') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
    <div class="field mb-0">
        <label class="label" for="kolo">{{ __('pages.pribezne.filter_round') }}</label>
        <select id="kolo" name="kolo" class="select w-auto">
            @foreach ($kola as $k)
                <option value="{{ $k->id }}" @selected($kolo && $k->id === $kolo->id)>{{ $k->nazev }} ({{ $k->datum_konani?->format('j. n. Y') }})</option>
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
    <button type="submit" class="btn btn-primary">{{ __('pages.pribezne.btn_show') }}</button>
</form>

@if (! $kolo)
    <p class="text-muted">{{ __('pages.pribezne.no_round') }}</p>
@elseif ($vysledky->isEmpty())
    <p class="text-muted">{{ __('pages.pribezne.no_results') }}</p>
@else
@foreach ($vysledky->groupBy('id_kategorie') as $katId => $radky)
    <div class="section-head">{{ __('pages.hlaseni.interim_results') }} — {{ $kategorie[$katId]->nazev ?? ('kategorie ' . $katId) }}</div>
    <div class="table-wrap mb-4">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="num">{{ __('pages.hlaseni.col_pos') }}</th>
                    <th>{{ __('pages.hlaseni.col_callsign') }}</th>
                    <th>{{ __('pages.hlaseni.col_locator') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_qso') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_mult') }}</th>
                    <th class="num">{{ __('pages.hlaseni.col_total') }}</th>
                    <th>{{ __('pages.hlaseni.col_name_note') }}</th>
                    <th>{{ __('pages.hlaseni.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($radky as $i => $r)
                <tr @class(['row-pending' => ! $r->schvaleno])>
                    <td class="num font-bold">{{ $i + 1 }}.</td>
                    <td class="mono font-bold">{{ $r->znacka }}{{ $r->qrp ? ' /QRP' : '' }}</td>
                    <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                    <td class="num">{{ (int) $r->pocet }}</td>
                    <td class="num">{{ (int) $r->nasobice }}</td>
                    <td class="num font-bold">{{ (int) $r->body }}</td>
                    <td class="text-muted">{{ $r->jmeno }} @if ($r->poznamka)<i>({{ $r->poznamka }})</i>@endif</td>
                    <td>
                        @if ($r->schvaleno)
                            <span class="badge badge-ok">{{ __('pages.hlaseni.status_ok') }}</span>
                        @else
                            <span class="badge badge-warn">{{ __('pages.hlaseni.status_pending') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
@endif

@push('scripts')
<script>
(function () {
    var form = document.getElementById('kolo')?.closest('form');
    if (form) {
        document.getElementById('kolo').addEventListener('change', function () { form.submit(); });
        document.getElementById('kategorie').addEventListener('change', function () { form.submit(); });
    }
}());
</script>
@endpush
@endsection
