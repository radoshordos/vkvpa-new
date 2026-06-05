{{--
    Výsledková listina.

    Výsledky vybraného kola rozdělené po kategoriích (144 MHz single op,
    144 MHz multi op, 432 MHz single op …), s vyhledáváním a – pro admina –
    se sloupcem „Akce / EDI".

      Filtr:   Kolo / Round (select)  |  Hledat / Search (značka / lokátor)
      Sloupce: Poř. | Značka+Jméno+čas | Locator | QSO | Nás./Mult.
               | Body (+ b/QSO) | Soapbox/Poznámka | Akce / EDI
      Odznak:  QRP u QRP stanic
      Pozadí:  zvýrazněné = nepřevzato (vyhodnocovatel záznam ještě neviděl);
               vidí ho jen admin – host vidí pouze převzaté (schvaleno=1).
      Vzorec:  b/QSO = body / (násobiče × QSO)

    Sloupec „Akce / EDI" – 8 akcí ve třech řádcích (jen pro přihlášeného admina):
      P    PŘEVZÍT záznam     → route('zaznam.update', ['zaznam' => $r->id])  PATCH
      U    upravit záznam     → route('hlaseni.index', ['id' => $r->id])
      X    smazat záznam      → route('zaznam.destroy', ['zaznam' => $r->id])  DELETE
      EDI  původní EDI soubor → route('edi.soubor', ['head' => …])
      EDIR redukovaný EDI (08–11 UTC) → route('edi.soubor.redukovany', ['head' => …])
      M    mapa „ježek"       → route('edi.mapa.jezek', ['head' => …])
      N    mapa se špendlíky   → route('edi.mapa.spendliky', ['head' => …])
      S    mapa velkých čtverců → route('edi.mapa.lokatory', ['head' => …])
--}}
@extends('layouts.app')
@section('title', 'Výsledková listina – VKV PA')

@section('content')
@php $isAdmin = (bool) (auth()->user()?->is_admin); @endphp

<h1>Výsledková listina / Results</h1>

<form method="get" action="{{ route('vysledkova_listina') }}" class="card mb-4 flex flex-wrap items-end gap-4 p-3">
    <div class="field mb-0">
        <label class="label" for="kolo">Kolo / Round</label>
        <select id="kolo" name="kolo" class="select w-auto">
            @foreach ($kola as $k)
                <option value="{{ $k->id }}" @selected($kolo && $k->id === $kolo->id)>{{ $k->nazev }} ({{ $k->datum_konani?->format('j.n.Y') }})</option>
            @endforeach
        </select>
    </div>
    <div class="field mb-0">
        <label class="label" for="hledat">Hledat / Search</label>
        <input id="hledat" type="text" name="hledat" value="{{ $hledat }}" placeholder="Callsign / Locator…" class="input w-48">
    </div>
    <label class="flex items-center gap-2 pb-2 text-sm">
        <input id="qrp" type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp'))> jen QRP
    </label>
    <button type="submit" class="btn btn-primary">Zobrazit / Show</button>
</form>

@if (! $kolo)
    <p class="text-muted">Žádné uzavřené kolo k zobrazení.</p>
@elseif ($limitReached ?? false)
    <div class="alert alert-error">Výsledků je příliš mnoho – zobrazeno je jen prvních {{ $radky->count() }} záznamů. Použijte filtr pro upřesnění.</div>
@elseif ($radky->isEmpty())
    @if ($hledat !== '')
        <p class="text-muted">Hledání „{{ $hledat }}" v tomto kole nevrátilo žádné výsledky.</p>
    @else
        <p class="text-muted">Pro toto kolo zatím nejsou žádné výsledky.</p>
    @endif
@else
    @foreach ($radky->groupBy('id_kategorie') as $katId => $skupina)
        <div class="section-head">{{ $kategorie[$katId]->nazev ?? ('Kategorie ' . $katId) }}</div>
        <div class="table-wrap mb-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="num">Poř.</th>
                        <th>Značka / Jméno</th>
                        <th>Locator</th>
                        <th class="num">QSO</th>
                        <th class="num">Nás.</th>
                        <th class="num">Body</th>
                        <th>Soapbox / Poznámka</th>
                        @if ($isAdmin)<th>Akce / EDI</th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach ($skupina as $i => $r)
                    @php
                        $poradi = $r->poradi > 0 ? $r->poradi : $i + 1;
                        $bq = ($r->nasobice > 0 && $r->pocet > 0)
                            ? $r->body / ($r->nasobice * $r->pocet)
                            : 0.0;
                    @endphp
                    <tr @class(['row-pending' => ! $r->schvaleno])>
                        <td class="num font-bold">{{ $poradi }}.</td>
                        <td>
                            <span class="mono font-bold">{{ $r->znacka }}</span>@if ($r->qrp)<span class="badge badge-qrp ml-1">QRP</span>@endif
                            @if ($r->jmeno)<br><span class="text-muted">{{ $r->jmeno }}</span>@endif
                            @if ($r->timestamp)<br><span class="text-xs text-muted">{{ $r->timestamp->format('d.m. H:i') }}</span>@endif
                        </td>
                        <td class="mono whitespace-nowrap">{{ $r->locator }}</td>
                        <td class="num">{{ $r->pocet }}</td>
                        <td class="num">{{ $r->nasobice }}</td>
                        <td class="num">
                            <span class="font-bold text-warn">{{ $r->body }}</span><br>
                            <span class="text-xs text-muted">{{ number_format($bq, 1, ',', '') }} b/QSO</span>
                        </td>
                        <td class="text-danger">{{ $r->soapbox }}@if ($r->poznamka)<br><i class="text-muted">{{ $r->poznamka }}</i>@endif</td>
                        @if ($isAdmin)
                            <td>
                                {{-- 1. řádek: P převzít · U upravit · X smazat --}}
                                <div class="mb-1 flex items-center gap-1">
                                    <form method="post" action="{{ route('zaznam.update', ['zaznam' => $r->id]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="icon-btn icon-btn-p"
                                                title="{{ $r->schvaleno ? 'Záznam je převzat' : 'Převzít záznam (vyhodnocovatel viděl)' }}">P</button>
                                    </form>
                                    <a href="{{ route('hlaseni.index', ['id' => $r->id]) }}" class="icon-btn icon-btn-u" title="Upravit záznam">U</a>
                                    <form method="post" action="{{ route('zaznam.destroy', ['zaznam' => $r->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="icon-btn icon-btn-x" title="Smazat záznam"
                                                onclick="openDelModal(this, @js($r->znacka))">X</button>
                                    </form>
                                </div>
                                @if ($r->EDI && $r->EDI_ID)
                                    {{-- 2. řádek: EDI · EDIR --}}
                                    <div class="whitespace-nowrap">
                                        <a href="{{ route('edi.soubor', ['head' => $r->EDI_ID]) }}" class="action-link" title="Zobrazit původní EDI soubor">EDI</a>
                                        <a href="{{ route('edi.soubor.redukovany', ['head' => $r->EDI_ID]) }}" class="action-link" title="Zobrazit redukovaný EDI (08–11 UTC)">EDIR</a>
                                    </div>
                                    {{-- 3. řádek: mapy M · N · S --}}
                                    <div class="whitespace-nowrap">
                                        <a href="{{ route('edi.mapa.jezek', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – ježek (čáry do protistanic)">M</a>
                                        <a href="{{ route('edi.mapa.spendliky', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – špendlíky (značka, km, azimut)">N</a>
                                        <a href="{{ route('edi.mapa.lokatory', ['head' => $r->EDI_ID]) }}" class="action-link" title="Mapa – velké čtverce s počty protistanic">S</a>
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

{{-- Modal pro potvrzení smazání záznamu --}}
<div id="del-overlay" role="dialog" aria-modal="true" aria-labelledby="del-modal-title"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
    <div class="card w-full max-w-sm p-5">
        <h2 id="del-modal-title" class="mb-2 text-base font-bold text-danger">Smazat záznam</h2>
        <p id="del-modal-msg" class="mb-4 text-sm text-ink"></p>
        <div class="flex justify-end gap-2">
            <button type="button" id="del-cancel" class="btn btn-ghost btn-sm">Zrušit</button>
            <button type="button" id="del-confirm" class="btn btn-danger btn-sm">Smazat</button>
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

    window.openDelModal = function (btn, znacka) {
        pending = btn.closest('form');
        msgEl.textContent = 'Opravdu smazat záznam ' + znacka + '?';
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
