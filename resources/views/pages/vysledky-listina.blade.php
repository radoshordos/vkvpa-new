{{--
    Výsledková listina.
--}}
@extends('layouts.app')
@section('title', __('pages.vysledky.title'))
@section('meta_description', __('pages.vysledky.meta'))
{{-- Canonical = hezké URL konkrétního kola; sjednocuje ?kolo=/filtry, aby se
     listiny jednotlivých kol navzájem nekanibalizovaly. --}}
@section('canonical', $kolo ? route('vysledkova_listina', ['kolo' => $kolo->id]) : route('vysledkova_listina'))

@if ($kolo)
    @section('jsonld')
        @include('partials.jsonld-kolo', ['kolo' => $kolo])
    @endsection
@endif

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
    @if ($isAdmin)
        <div class="field mb-0">
            <label class="label" for="prevzeti">{{ __('pages.vysledky.filter_prevzeti') }}</label>
            <select id="prevzeti" name="prevzeti" class="select w-auto">
                <option value="all" @selected(($prevzeti ?? 'all') === 'all')>{{ __('pages.vysledky.prevzeti_all') }}</option>
                <option value="yes" @selected(($prevzeti ?? 'all') === 'yes')>{{ __('pages.vysledky.prevzeti_yes') }}</option>
                <option value="no" @selected(($prevzeti ?? 'all') === 'no')>{{ __('pages.vysledky.prevzeti_no') }}</option>
            </select>
        </div>
    @endif
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
                        <th>{{ __('pages.vysledky.col_edi') }}</th>
                        <th>{{ __('pages.vysledky.col_stats') }}</th>
                        @if ($isAdmin)<th>{{ __('pages.vysledky.col_actions') }}</th>@endif
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
                            @if ($isAdmin)
                                <a href="{{ route('uzivatele.index', ['kolo' => $r->id_kola, 'q' => $r->znacka]) }}" class="link mono font-bold" title="{{ __('pages.vysledky.link_contact') }}">{{ $r->znacka }}</a>
                            @elseif (preg_match('/^[A-Za-z0-9]+$/', $r->znacka))
                                <a href="{{ route('statistiky.stanice', ['znacka' => $r->znacka]) }}" class="link mono font-bold" title="{{ __('pages.stat.stanice_subtitle') }}">{{ $r->znacka }}</a>
                            @else
                                <span class="mono font-bold">{{ $r->znacka }}</span>
                            @endif
                            <x-vykon-badge :vykon="$r->vykon()" /> @if ($sk['top'])<x-badge variant="skokan" class="ml-1" title="{{ __('pages.vysledky.skokan_title') }}">SKOKAN</x-badge>@endif
                            @if ($r->jmeno)<br><span class="text-muted">{{ $r->jmeno }}</span>@endif
                            @if ($r->timestamp)<br><span class="text-xs text-muted">{{ $r->timestamp->format('j. n. H:i') }}</span>@endif
                        </td>
                        <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                        <td class="num">{{ $r->pocet }}</td>
                        <td class="num">{{ $r->nasobice }}</td>
                        <td class="num">
                            <span class="font-bold text-warn">{{ $r->body }}</span><br>
                            <span class="text-xs text-muted">{{ \Illuminate\Support\Number::format($bq, 1) }} b/QSO</span>
                            @if ($sk['delta'] !== null)
                                <br>
                                @if ($sk['delta'] > 0)
                                    <span class="text-xs font-bold text-ok" title="{{ __('pages.vysledky.vs_last_start') }}">▲ +{{ $sk['delta'] }}</span>
                                @elseif ($sk['delta'] < 0)
                                    <span class="text-xs font-bold text-danger" title="{{ __('pages.vysledky.vs_last_start') }}">▼ {{ $sk['delta'] }}</span>
                                @else
                                    <span class="text-xs text-muted" title="{{ __('pages.vysledky.same_as_last') }}">→ 0</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-danger">{{ $r->soapbox }}@if ($r->poznamka)<br><i class="text-muted">{{ $r->poznamka }}</i>@endif</td>
                        <td>
                            @if ($r->edihead_id)
                                {{-- EDI · EDIR: admin vždy, ostatní jen mimo upload window --}}
                                <div class="flex items-center gap-1">
                                    @if ($isAdmin || ! $uploadWindowOpen)
                                        <x-edi-odkaz :head="$r->edihead_id" />
                                        <x-edi-odkaz :head="$r->edihead_id" reduced />
                                    @else
                                        <span class="action-link cursor-not-allowed opacity-50" title="{{ __('app.edi_restricted_body') }}">{{ __('app.edi_restricted_label') }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($r->edihead_id)
                                {{-- Statistiky deníku – veřejné vždy --}}
                                <x-vizualizace-odkaz :head="$r->edihead_id" />
                            @endif
                        </td>
                        @if ($isAdmin)
                            <td>@include('partials.zaznam-akce', ['r' => $r, 'bezEdi' => true, 'prijemOtevren' => $kolo->prijimaHlaseni()])</td>
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
    var filterForm = document.getElementById('kolo')?.closest('form');
    if (filterForm) {
        document.getElementById('kolo').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('qrp').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('lp').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('prevzeti')?.addEventListener('change', function () { filterForm.submit(); });
    }
}());
</script>
@endpush
@endsection
