{{--
    Průběžné výsledky kola – samostatná veřejná stránka.
    Tabulka je shodná se spodní částí stránky „Načíst EDI soubor"
    (včetně nepřevzatých hlášení = stav „Čeká"), navíc s filtry kola a kategorie.
--}}
@extends('layouts.app')
@section('title', __('pages.pribezne.title'))

@section('content')
@php $isAdmin = (bool) (auth()->user()?->is_admin); @endphp

<h1>{{ __('pages.pribezne.heading') }}</h1>

@if ($kolo)
<form method="get" action="{{ route('pribezne_vysledky') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
    <div class="field mb-0">
        <span class="label">{{ __('pages.pribezne.filter_round') }}</span>
        <strong>{{ $kolo->nazev }} ({{ $kolo->datum_konani?->format('j. n. Y') }})</strong>
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
@endif

@if (! $kolo)
    <x-alert type="info" :message="__('pages.pribezne.no_round')" />
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
                    @if ($isAdmin)<th>{{ __('pages.vysledky.col_actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
            @foreach ($radky as $i => $r)
                <tr @class(['row-pending' => ! $r->schvaleno, 'group' => $isAdmin])>
                    <td class="num font-bold">{{ $i + 1 }}.</td>
                    <td class="mono font-bold">{{ $r->znacka }}{{ $r->qrp ? ' /QRP' : '' }}</td>
                    <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                    <td class="num">{{ (int) $r->pocet }}</td>
                    <td class="num">{{ (int) $r->nasobice }}</td>
                    <td class="num font-bold">{{ (int) $r->body }}</td>
                    <td class="text-muted">{{ $r->jmeno }} @if ($r->poznamka)<i>({{ $r->poznamka }})</i>@endif</td>
                    <td>
                        @if ($r->schvaleno)
                            <x-badge variant="ok">{{ __('pages.hlaseni.status_ok') }}</x-badge>
                        @else
                            <x-badge variant="warn">{{ __('pages.hlaseni.status_pending') }}</x-badge>
                        @endif
                    </td>
                    @if ($isAdmin)
                        <td>@include('partials.zaznam-akce', ['r' => $r])</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
@endif

@if ($isAdmin)
    @include('partials.del-modal')
@endif

@push('scripts')
<script @cspNonce>
(function () {
    var kategorie = document.getElementById('kategorie');
    var form = kategorie ? kategorie.closest('form') : null;
    if (form) {
        kategorie.addEventListener('change', function () { form.submit(); });
    }
}());
</script>
@endpush
@endsection
