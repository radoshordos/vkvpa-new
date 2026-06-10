{{--
    Výsledková listina.
--}}
@extends('layouts.app')
@section('title', __('pages.vysledky.title'))

@section('content')
@php
    $isAdmin = (bool) (auth()->user()?->is_admin);
    $uploadWindowOpen = $uploadWindowOpen ?? false;
@endphp

<h1>{{ __('pages.vysledky.heading') }}</h1>

<form method="get" action="{{ route('vysledkova_listina') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
    <div class="field mb-0">
        <label class="label" for="kolo">{{ __('pages.vysledky.filter_round') }}</label>
        <select id="kolo" name="kolo" class="select w-auto">
            @foreach ($kola as $k)
                <option value="{{ $k->id }}" @selected($kolo && $k->id === $kolo->id)>{{ $k->nazev }} ({{ $k->datum_konani?->format('j. n. Y') }})</option>
            @endforeach
        </select>
    </div>
    <div class="field mb-0">
        <label class="label" for="hledat">{{ __('pages.vysledky.filter_search') }}</label>
        <input id="hledat" type="text" name="hledat" value="{{ $hledat }}" placeholder="Callsign / Locator…" class="input w-48">
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm">
        <input id="qrp" type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp'))> {{ __('pages.vysledky.filter_qrp') }}
    </label>
    <label class="flex items-center gap-2 pb-2 text-sm">
        <input id="lp" type="checkbox" name="lp" value="1" @checked(request()->boolean('lp'))> {{ __('pages.vysledky.filter_lp') }}
    </label>
    <button type="submit" class="btn btn-primary">{{ __('pages.vysledky.btn_show') }}</button>
</form>

@if (! $kolo)
    <p class="text-muted">{{ __('pages.vysledky.no_closed_round') }}</p>
@elseif ($limitReached ?? false)
    <x-alert type="error" :message="__('pages.vysledky.too_many', ['count' => $radky->count()])" />
@elseif ($radky->isEmpty())
    @if ($hledat !== '')
        <p class="text-muted">{{ __('pages.vysledky.no_search', ['query' => $hledat]) }}</p>
    @else
        <p class="text-muted">{{ __('pages.vysledky.no_results') }}</p>
    @endif
@else
    @foreach ($radky->groupBy('id_kategorie') as $katId => $skupina)
        <div class="section-head">{{ $kategorie[$katId]->nazev ?? ('Kategorie ' . $katId) }}</div>
        <div class="table-wrap mb-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="num">{{ __('pages.vysledky.col_pos') }}</th>
                        <th>{{ __('pages.vysledky.col_callsign') }}</th>
                        <th>{{ __('pages.vysledky.col_locator') }}</th>
                        <th class="num">{{ __('pages.vysledky.col_qso') }}</th>
                        <th class="num">{{ __('pages.vysledky.col_mult') }}</th>
                        <th class="num">{{ __('pages.vysledky.col_total') }}</th>
                        <th>{{ __('pages.vysledky.col_soapbox') }}</th>
                        <th>{{ __('pages.vysledky.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($skupina as $i => $r)
                    @php
                        $poradi = $r->poradi > 0 ? $r->poradi : $i + 1;
                        $bq = ($r->nasobice > 0 && $r->pocet > 0)
                            ? $r->body / ($r->nasobice * $r->pocet)
                            : 0.0;
                        $sk = $skokani[$r->id] ?? ['delta' => null, 'top' => false];
                    @endphp
                    <tr @class(['row-pending' => ! $r->schvaleno, 'group'])>
                        <td class="num font-bold">{{ $poradi }}.</td>
                        <td>
                            <span class="mono font-bold">{{ $r->znacka }}</span>@if ($r->qrp)<x-badge variant="qrp" class="ml-1">QRP</x-badge>@elseif ($r->lp)<x-badge class="ml-1">LP</x-badge>@endif @if ($sk['top'])<x-badge variant="skokan" class="ml-1" title="Největší skokan v kategorii (oproti poslednímu startu)">SKOKAN</x-badge>@endif
                            @if ($r->jmeno)<br><span class="text-muted">{{ $r->jmeno }}</span>@endif
                            @if ($r->timestamp)<br><span class="text-xs text-muted">{{ $r->timestamp->format('j. n. H:i') }}</span>@endif
                        </td>
                        <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                        <td class="num">{{ $r->pocet }}</td>
                        <td class="num">{{ $r->nasobice }}</td>
                        <td class="num">
                            <span class="font-bold text-warn">{{ $r->body }}</span><br>
                            <span class="text-xs text-muted">{{ number_format($bq, 1, ',', '') }} b/QSO</span>
                            @if ($sk['delta'] !== null)
                                <br>
                                @if ($sk['delta'] > 0)
                                    <span class="text-xs font-bold text-ok" title="oproti poslednímu startu">▲ +{{ $sk['delta'] }}</span>
                                @elseif ($sk['delta'] < 0)
                                    <span class="text-xs font-bold text-danger" title="oproti poslednímu startu">▼ {{ $sk['delta'] }}</span>
                                @else
                                    <span class="text-xs text-muted" title="stejně jako posledně">→ 0</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-danger">{{ $r->soapbox }}@if ($r->poznamka)<br><i class="text-muted">{{ $r->poznamka }}</i>@endif</td>
                        <td>
                            @if ($isAdmin)
                                @include('partials.zaznam-akce', ['r' => $r])
                            @elseif ($r->edihead_id)
                                {{-- EDI · EDIR · vizualizace: veřejnost i přihlášení (ne-admin) jen mimo upload window --}}
                                <div class="flex items-center gap-1">
                                    @if (! $uploadWindowOpen)
                                        <x-edi-odkaz :head="$r->edihead_id" />
                                        <x-edi-odkaz :head="$r->edihead_id" reduced />
                                        <x-vizualizace-odkaz :head="$r->edihead_id" />
                                    @else
                                        <span class="action-link cursor-not-allowed opacity-50" title="{{ __('app.edi_restricted_body') }}">{{ __('app.edi_restricted_label') }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
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
    var filterForm = document.getElementById('kolo')?.closest('form');
    if (filterForm) {
        document.getElementById('kolo').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('qrp').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('lp').addEventListener('change', function () { filterForm.submit(); });
    }
}());
</script>
@endpush
@endsection
