{{--
    Výsledková listina.
--}}
@extends('layouts.app')
@section('title', __('pages.vysledky.title'))

@section('content')
@php
    $isAdmin = (bool) (auth()->user()?->is_admin);
    $isAuth  = auth()->check();
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
    <div class="alert alert-error">{{ __('pages.vysledky.too_many', ['count' => $radky->count()]) }}</div>
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
                        @if ($isAdmin || $isAuth)<th>{{ __('pages.vysledky.col_actions') }}</th>@endif
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
                            <span class="mono font-bold">{{ $r->znacka }}</span>@if ($r->qrp)<span class="badge badge-qrp ml-1">QRP</span>@elseif ($r->lp)<span class="badge ml-1">LP</span>@endif @if ($sk['top'])<span class="badge badge-skokan ml-1" title="Největší skokan v kategorii (oproti poslednímu startu)">SKOKAN</span>@endif
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
                        @if ($isAdmin || $isAuth)
                            <td>
                                @if ($isAdmin)
                                    {{-- 1. řádek: P převzít · U upravit · X smazat --}}
                                    <div class="mb-1 flex items-center gap-1">
                                        <form method="post" action="{{ route('zaznam.update', ['zaznam' => $r->id]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" @class(['icon-btn', 'icon-btn-p', 'icon-btn-p-off' => ! $r->schvaleno])
                                                    title="{{ $r->schvaleno ? 'Vrátit mezi nepřevzaté (odebrat převzetí)' : 'Převzít záznam (vyhodnocovatel viděl)' }}">
                                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="2 8.5 6 12.5 14 3.5"/></svg>
                                            </button>
                                        </form>
                                        <a href="{{ route('hlaseni.index', ['id' => $r->id]) }}" class="icon-btn icon-btn-u" title="Upravit záznam">
                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.5 2.5a1.5 1.5 0 0 1 2 2L5 13l-3 1 1-3 8.5-8.5z"/></svg>
                                        </a>
                                        <form method="post" action="{{ route('zaznam.destroy', ['zaznam' => $r->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="icon-btn icon-btn-x" title="Smazat záznam"
                                                    onclick="openDelModal(this, @js($r->znacka))">
                                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="2" y1="4" x2="14" y2="4"/><path d="M5 4V2.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 .5.5V4M13 4v9a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 3 13V4"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                                @if ($r->EDI && $r->EDI_ID)
                                    {{-- EDI · EDIR: admin vždy, ostatní přihlášení jen mimo upload window --}}
                                    <div class="whitespace-nowrap opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                        @if ($isAdmin || ! $uploadWindowOpen)
                                            <a href="{{ route('edi.soubor', ['head' => $r->EDI_ID]) }}" class="action-link" title="Zobrazit původní EDI soubor">EDI</a>
                                            <a href="{{ route('edi.soubor.redukovany', ['head' => $r->EDI_ID]) }}" class="action-link" title="Zobrazit redukovaný EDI (08–11 UTC)">EDIR</a>
                                        @else
                                            <span class="action-link cursor-not-allowed opacity-50" title="{{ __('app.edi_restricted_body') }}">{{ __('app.edi_restricted_label') }}</span>
                                        @endif
                                    </div>
                                    @if ($isAdmin)
                                        {{-- Mapy M · N · S · C · V – jen admin --}}
                                        <div class="whitespace-nowrap opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                            <a href="{{ route('edi.mapa.jezek', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – ježek (čáry do protistanic)">M</a>
                                            <a href="{{ route('edi.mapa.spendliky', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – špendlíky (značka, km, azimut)">N</a>
                                            <a href="{{ route('edi.mapa.lokatory', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – velké čtverce s počty protistanic">S</a>
                                            <a href="{{ route('edi.mapa.crk', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – kombinovaná (paprsky, provoz, kružnice, mřížka, stanice z kola)">C</a>
                                            <a href="{{ route('edi.vizualizace', ['head' => $r->EDI_ID]) }}" class="action-link" title="Vizualizace deníku (mapa + grafy)">V</a>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif

{{-- Modal pro potvrzení smazání záznamu --}}
<div id="del-overlay" role="dialog" aria-modal="true" aria-labelledby="del-modal-title"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
    <div class="card w-full max-w-sm p-5">
        <h2 id="del-modal-title" class="mb-2 text-base font-bold text-danger">{{ __('pages.vysledky.delete_title') }}</h2>
        <p id="del-modal-msg" class="mb-4 text-sm text-ink"></p>
        <div class="flex justify-end gap-2">
            <button type="button" id="del-cancel" class="btn btn-ghost btn-sm">{{ __('pages.vysledky.btn_cancel') }}</button>
            <button type="button" id="del-confirm" class="btn btn-danger btn-sm">{{ __('pages.vysledky.btn_delete') }}</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var filterForm = document.getElementById('kolo')?.closest('form');
    if (filterForm) {
        document.getElementById('kolo').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('qrp').addEventListener('change', function () { filterForm.submit(); });
        document.getElementById('lp').addEventListener('change', function () { filterForm.submit(); });
    }
}());
</script>
<script>
(function () {
    var overlay    = document.getElementById('del-overlay');
    var msgEl      = document.getElementById('del-modal-msg');
    var confirmBtn = document.getElementById('del-confirm');
    var cancelBtn  = document.getElementById('del-cancel');
    var pending    = null;
    var confirmTpl = @js(__('pages.vysledky.delete_confirm', ['callsign' => ':callsign']));

    window.openDelModal = function (btn, znacka) {
        pending = btn.closest('form');
        msgEl.textContent = confirmTpl.replace(':callsign', znacka);
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        confirmBtn.focus();
    };

    confirmBtn.addEventListener('click', function () {
        close();
        if (pending) { pending.submit(); }
    });

    function close() {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        pending = null;
    }

    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ! overlay.classList.contains('hidden')) close();
    });
}());
</script>
@endpush
@endsection
