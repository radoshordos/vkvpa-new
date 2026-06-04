{{--
    Výsledková listina.

    Výsledky vybraného kola rozdělené po kategoriích (144 MHz single op,
    144 MHz multi op, 432 MHz single op …), s vyhledáváním a – pro admina –
    se sloupcem „Akce / EDI".

    Referenční podoba z původní aplikace (screenshoty 05/2026):
      Filtr:   Kolo / Round (select)  |  Hledat / Search (značka / lokátor)
      Sloupce: Poř. | Značka+Jméno+čas | Locator | QSO | Nás./Mult.
               | Body (+ b/QSO) | Soapbox/Poznámka | Akce / EDI
      Odznak:  QRP (zeleně) u QRP stanic
      Pozadí:  meruňkové = nepřevzato (vyhodnocovatel záznam ještě neviděl);
               vidí ho jen admin – host vidí pouze převzaté (schvaleno=1).
      Vzorec:  b/QSO = body / (násobiče × QSO)

    Sloupec „Akce / EDI" – 8 akcí ve třech řádcích (jen pro přihlášeného admina):
      ── 1. řádek (barevná tlačítka, jen admin) ──────────────────────────────
      P    PŘEVZÍT záznam – vyhodnocovatel ho viděl; zmizí meruňkové pozadí
                              → route('zaznam.update', ['zaznam' => $r->id])  PATCH ✅
      U    upravit záznam – načte ho zpět do formuláře k editaci
                              → route('hlaseni.index', ['id' => $r->id])       ✅
      X    smazat záznam     → route('zaznam.destroy', ['zaznam' => $r->id])  DELETE ✅
      ── 2. řádek (EDI, modré odkazy) ────────────────────────────────────────
      EDI  zobrazit původní EDI soubor   → route('edi.soubor', ['head' => …])   ✅
      EDIR zobrazit REDUKOVANÝ EDI – oříznutý jen na časové okno závodu
           (QSO mezi 08:00–11:00 UTC); tato podoba se zároveň vyhodnocuje.
                          → route('edi.soubor.redukovany', ['head' => …])  ✅
      ── 3. řádek (mapy, červené odkazy) ─────────────────────────────────────
      M    mapa „ježek": z QTH vedou čáry do protistanic
                              → route('edi.mapa.jezek', ['head' => …])      ✅
      N    mapa se špendlíky protistanic; popup = značka, vzdálenost (km),
           azimut (°)         → route('edi.mapa.spendliky', ['head' => …])  ✅
           (inspirace: https://vushf.dk/contest/map_details.php)
      S    mapa velkých čtverců (lokátorů) s počtem protistanic v každém
                              → route('edi.mapa.lokatory', ['head' => …])   ✅

--}}
@extends('layouts.app')
@section('title', 'Výsledková listina – VKV PA')

@push('head')
<style>
    .vysl-filter { background:#eee; border:1px solid #ccc; padding:10px; margin-bottom:15px; font-size:13px; }
    .vysl-filter input[type=text], .vysl-filter select { border:1px solid #777; padding:2px; background:white; font-size:13px; }
    .vysl-kat { background:#1a3a8c; color:#fff; font-weight:bold; font-size:15px; padding:6px 12px; margin-top:18px; }
    table.vysl { width:100%; border-collapse:separate; border-spacing:1px; background:#b4b4b4; font-size:13px; font-family:Arial, sans-serif; }
    table.vysl th { background:#e6e6fa; color:#000080; font-weight:bold; padding:4px 8px; text-align:left; }
    table.vysl td { padding:5px 8px; vertical-align:top; background:#fff; }
    table.vysl tr:nth-child(even) td { background:#f4eff1; }
    /* Meruňková = nepřevzato (vyhodnocovatel záznam ještě neviděl); vidí jen admin. */
    table.vysl tr.nepr td { background:#ffdab9; }
    .vysl-body { color:#b35a00; font-weight:bold; font-size:15px; }
    .vysl-bq { color:#666; font-size:11px; }
    .vysl-date { color:#999; font-size:11px; }
    .vysl-jmeno { color:#555; }
    .vysl-soap { color:#cc0000; font-size:12px; }
    .vysl-qrp { background:#2db62f; color:#fff; font-size:10px; font-weight:bold; padding:0 4px; border-radius:3px; margin-left:5px; vertical-align:middle; }
    .vysl-akce a { color:#1a3a8c; text-decoration:underline; font-size:11px; display:inline-block; margin-right:6px; }
    /* Sloupec „Akce / EDI" – barevná tlačítka P/U/X (1. řádek). */
    .akce-row { white-space:nowrap; margin-bottom:3px; }
    .akce-row form { display:inline; margin:0; }
    .akce-btn { display:inline-block; width:18px; height:18px; line-height:18px; text-align:center;
                font-weight:bold; font-size:11px; color:#fff; border:none; border-radius:2px;
                cursor:pointer; text-decoration:none; margin-right:2px; padding:0; font-family:Arial, sans-serif; }
    .akce-p { background:#2db62f; }  /* P – schválit (zelené)  */
    .akce-u { background:#1a5fb4; }  /* U – upravit  (modré)   */
    .akce-x { background:#cc2222; }  /* X – smazat   (červené) */
    .num { text-align:right; }
    /* Modal pro potvrzení smazání */
    #del-overlay {
        display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
        z-index:9999; align-items:center; justify-content:center;
    }
    #del-overlay.open { display:flex; }
    #del-modal {
        background:#fff; border:2px solid #cc2222; border-radius:4px;
        padding:20px 24px; max-width:360px; width:100%;
        font-family:Arial, sans-serif; font-size:13px;
    }
    #del-modal h2 { color:#cc2222; font-size:14px; font-weight:bold; margin-bottom:10px; border:none; }
    #del-modal p  { margin-bottom:16px; color:#333; }
    #del-modal .modal-btns { display:flex; gap:8px; justify-content:flex-end; }
    .del-btn { display:inline-block; padding:3px 14px; font-weight:bold; font-size:12px;
               color:#fff; border:none; border-radius:2px; cursor:pointer;
               font-family:Arial, sans-serif; }
    .del-btn-cancel  { background:#888; }
    .del-btn-confirm { background:#cc2222; }
</style>
@endpush

@section('content')
@php $isAdmin = (bool) (auth()->user()?->is_admin); @endphp

<h1>Výsledková listina / Results</h1>

<form method="get" action="{{ route('vysledkova_listina') }}" class="vysl-filter">
    <b>Kolo / Round:</b>
    <select name="kolo" onchange="this.form.submit()">
        @foreach ($kola as $k)
            <option value="{{ $k->id }}" @selected($kolo && $k->id === $kolo->id)>{{ $k->nazev }} ({{ $k->datum_konani?->format('j.n.Y') }})</option>
        @endforeach
    </select>
    &nbsp;&nbsp;
    <b>Hledat / Search:</b>
    <input type="text" name="hledat" value="{{ $hledat }}" placeholder="Callsign / Locator…" size="20">
    &nbsp;&nbsp;
    <label><input type="checkbox" name="qrp" value="1" @checked(request()->boolean('qrp')) onchange="this.form.submit()"> jen QRP</label>
    &nbsp;&nbsp;
    <input type="submit" value="Zobrazit / Show">
</form>

@if (! $kolo)
    <p>Žádné uzavřené kolo k zobrazení.</p>
@elseif ($limitReached ?? false)
    <p style="color:#cc0000;font-weight:bold;">Výsledků je příliš mnoho – zobrazeno je jen prvních {{ $radky->count() }} záznamů. Použijte filtr pro upřesnění.</p>
@elseif ($radky->isEmpty())
    @if ($hledat !== '')
        <p>Hledání „{{ $hledat }}" v tomto kole nevrátilo žádné výsledky.</p>
    @else
        <p>Pro toto kolo zatím nejsou žádné výsledky.</p>
    @endif
@else
    @foreach ($radky->groupBy('id_kategorie') as $katId => $skupina)
        <div class="vysl-kat">{{ $kategorie[$katId]->nazev ?? ('Kategorie ' . $katId) }}</div>
        <table class="vysl">
            <tr>
                <th style="width:34px;">Poř.</th>
                <th style="width:170px;">Značka / Jméno</th>
                <th style="width:75px;">Locator</th>
                <th class="num" style="width:50px;">QSO</th>
                <th class="num" style="width:65px;">Nás./Mult.</th>
                <th class="num" style="width:95px;">Body</th>
                <th>Soapbox / Poznámka</th>
                @if ($isAdmin)<th style="width:90px;">Akce / EDI</th>@endif
            </tr>
            @foreach ($skupina as $i => $r)
                @php
                    $poradi = $r->poradi > 0 ? $r->poradi : $i + 1;
                    $bq = ($r->nasobice > 0 && $r->pocet > 0)
                        ? $r->body / ($r->nasobice * $r->pocet)
                        : 0.0;
                @endphp
                <tr @class(['nepr' => ! $r->schvaleno])>
                    <td><b>{{ $poradi }}.</b></td>
                    <td>
                        <b>{{ $r->znacka }}</b>@if ($r->qrp)<span class="vysl-qrp">QRP</span>@endif
                        @if ($r->jmeno)<br><span class="vysl-jmeno">{{ $r->jmeno }}</span>@endif
                        @if ($r->timestamp)<br><span class="vysl-date">{{ $r->timestamp->format('d.m. H:i') }}</span>@endif
                    </td>
                    <td>{{ $r->locator }}</td>
                    <td class="num">{{ $r->pocet }}</td>
                    <td class="num">{{ $r->nasobice }}</td>
                    <td class="num">
                        <span class="vysl-body">{{ $r->body }}</span><br>
                        <span class="vysl-bq">{{ number_format($bq, 1, ',', '') }} b/QSO</span>
                    </td>
                    <td class="vysl-soap">{{ $r->soapbox }}@if ($r->poznamka)<br><i>{{ $r->poznamka }}</i>@endif</td>
                    @if ($isAdmin)
                        <td class="vysl-akce">
                            {{-- 1. řádek: P schválit · U upravit · X smazat --}}
                            <div class="akce-row">
                                {{-- P – převzít záznam (PATCH zaznam.update); po převzetí zmizí meruňkové pozadí --}}
                                <form method="post" action="{{ route('zaznam.update', ['zaznam' => $r->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="akce-btn akce-p"
                                            title="{{ $r->schvaleno ? 'Záznam je převzat' : 'Převzít záznam (vyhodnocovatel viděl)' }}">P</button>
                                </form>
                                {{-- U – upravit záznam (GET, stránka hlášení s ?id) --}}
                                <a href="{{ route('hlaseni.index', ['id' => $r->id]) }}" class="akce-btn akce-u" title="Upravit záznam">U</a>
                                {{-- X – smazat záznam (DELETE zaznam.destroy, s modalem) --}}
                                <form method="post" action="{{ route('zaznam.destroy', ['zaznam' => $r->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="akce-btn akce-x" title="Smazat záznam"
                                            onclick="openDelModal(this, @js($r->znacka))">X</button>
                                </form>
                            </div>
                            @if ($r->EDI && $r->EDI_ID)
                                {{-- 2. řádek: EDI (původní deník) · EDIR (oříznutý na 08–11 UTC) --}}
                                <div class="akce-row">
                                    <a href="{{ route('edi.soubor', ['head' => $r->EDI_ID]) }}" title="Zobrazit původní EDI soubor">EDI</a>
                                    <a href="{{ route('edi.soubor.redukovany', ['head' => $r->EDI_ID]) }}" title="Zobrazit redukovaný EDI (08–11 UTC)">EDIR</a>
                                </div>
                                {{-- 3. řádek: mapy M (ježek) · N (špendlíky) · S (velké čtverce) --}}
                                <div class="akce-row">
                                    <a href="{{ route('edi.mapa.jezek', ['head' => $r->EDI_ID]) }}" title="Mapa – ježek (čáry do protistanic)">M</a>
                                    <a href="{{ route('edi.mapa.spendliky', ['head' => $r->EDI_ID]) }}" title="Mapa – špendlíky (značka, km, azimut)">N</a>
                                    <a href="{{ route('edi.mapa.lokatory', ['head' => $r->EDI_ID]) }}" title="Mapa – velké čtverce s počty protistanic">S</a>
                                </div>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endforeach
@endif

{{-- Modal pro potvrzení smazání záznamu --}}
<div id="del-overlay" role="dialog" aria-modal="true" aria-labelledby="del-modal-title">
    <div id="del-modal">
        <h2 id="del-modal-title">Smazat záznam</h2>
        <p id="del-modal-msg"></p>
        <div class="modal-btns">
            <button type="button" id="del-cancel"  class="del-btn del-btn-cancel">Zrušit</button>
            <button type="button" id="del-confirm" class="del-btn del-btn-confirm">Smazat</button>
        </div>
    </div>
</div>

@push('scripts')
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
        overlay.classList.add('open');
        confirmBtn.focus();
    };

    confirmBtn.addEventListener('click', function () {
        overlay.classList.remove('open');
        if (pending) { pending.submit(); }
    });

    function close() {
        overlay.classList.remove('open');
        pending = null;
    }

    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) close();
    });
}());
</script>
@endpush
@endsection
