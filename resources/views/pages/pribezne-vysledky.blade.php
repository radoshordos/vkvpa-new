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
                        <td>
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
                            @if ($r->EDI && $r->EDI_ID)
                                {{-- EDI · EDIR – admin má vždy přístup --}}
                                <div class="whitespace-nowrap opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                    <x-edi-odkaz :head="$r->EDI_ID" />
                                    <x-edi-odkaz :head="$r->EDI_ID" reduced />
                                </div>
                                {{-- Vizualizace deníku (mapy + grafy) – jen admin --}}
                                <div class="whitespace-nowrap opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                    <x-vizualizace-odkaz :head="$r->EDI_ID" />
                                </div>
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

@if ($isAdmin)
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
@endif

@push('scripts')
<script>
(function () {
    var kategorie = document.getElementById('kategorie');
    var form = kategorie ? kategorie.closest('form') : null;
    if (form) {
        kategorie.addEventListener('change', function () { form.submit(); });
    }
}());
</script>
@if ($isAdmin)
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
@endif
@endpush
@endsection
