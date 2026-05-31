{{--
    Výsledková listina (sladěno s legacy vysledky.php).
    Výsledky vybraného kola rozdělené po kategoriích (144 MHz single op,
    144 MHz multi op, 432 MHz single op …), s vyhledáváním a – pro admina –
    s odkazy na akce (úprava záznamu, mapa spojení z EDI).
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
    .vysl-body { color:#b35a00; font-weight:bold; font-size:15px; }
    .vysl-bq { color:#666; font-size:11px; }
    .vysl-date { color:#999; font-size:11px; }
    .vysl-jmeno { color:#555; }
    .vysl-soap { color:#cc0000; font-size:12px; }
    .vysl-qrp { background:#2db62f; color:#fff; font-size:10px; font-weight:bold; padding:0 4px; border-radius:3px; margin-left:5px; vertical-align:middle; }
    .vysl-akce a { color:#1a3a8c; text-decoration:underline; font-size:11px; display:inline-block; margin-right:6px; }
    .num { text-align:right; }
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
                <tr>
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
                            <a href="{{ route('edit_hlaseni', ['id' => $r->id]) }}">Upravit</a>
                            @if ($r->EDI && $r->EDI_ID)
                                <a href="{{ route('edi.mapa', ['head' => $r->EDI_ID]) }}">Mapa</a>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endforeach
@endif
@endsection
