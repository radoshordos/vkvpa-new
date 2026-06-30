@extends('layouts.app')
@section('title', __('admin.edi_manual_title'))

@php
    $maxKb = \App\Support\VkvpaSettings::ediMaxSizeKb();
@endphp

@push('head')
<style>
    .edi-manual code { white-space: nowrap; }
    .edi-manual pre code { white-space: pre; }
    .edi-manual .manual-anchor { scroll-margin-top: 5rem; }
</style>
@endpush

@section('content')
<article class="edi-manual">
    <h1>{{ __('admin.edi_manual_heading') }}</h1>
    <p class="max-w-3xl text-sm text-muted">
        Tento manuál popisuje aktuální chování importu v aplikaci: co musí mít soubor
        ve formátu REG1TEST, které hodnoty parser přijme, co import zastaví a co se
        pouze naimportuje s nulovým nebo sníženým bodovým dopadem.
    </p>

    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="card p-4">
            <span class="block text-xs uppercase tracking-wide text-muted">Soubor</span>
            <b class="mt-1 block text-heading">.edi / .txt, max {{ $maxKb }} kB</b>
            <p class="mt-1 text-xs text-muted">Platí pro veřejné nahrání, EDI debug i každý soubor uvnitř ZIP importu.</p>
        </div>
        <div class="card p-4">
            <span class="block text-xs uppercase tracking-wide text-muted">Kódování</span>
            <b class="mt-1 block text-heading">UTF-8 nebo Windows-1250</b>
            <p class="mt-1 text-xs text-muted">Windows-1250 se před parsováním převádí na UTF-8.</p>
        </div>
        <div class="card p-4">
            <span class="block text-xs uppercase tracking-wide text-muted">QSO řádek</span>
            <b class="mt-1 block text-heading">15 polí / 14 středníků</b>
            <p class="mt-1 text-xs text-muted">Jiný počet polí je strukturální chyba a import se odmítne celý.</p>
        </div>
        <div class="card p-4">
            <span class="block text-xs uppercase tracking-wide text-muted">Bodování</span>
            <b class="mt-1 block text-heading">08:00-11:00 UTC</b>
            <p class="mt-1 text-xs text-muted">Body se počítají z lokátorů, pole QSO-Points se ignoruje.</p>
        </div>
    </div>

    <nav class="mt-6 flex flex-wrap gap-2 text-sm">
        <a class="badge badge-brand" href="#struktura">Struktura</a>
        <a class="badge badge-brand" href="#hlavicka">Hlavička</a>
        <a class="badge badge-brand" href="#vykon">Výkon</a>
        <a class="badge badge-brand" href="#qso">QSO pole</a>
        <a class="badge badge-brand" href="#prijeti">Co projde importem</a>
        <a class="badge badge-brand" href="#skore">Bodování</a>
    </nav>

    <section id="struktura" class="manual-anchor mt-8">
        <h2>Základní struktura souboru</h2>
        <p class="max-w-3xl text-sm text-muted">
            Parser začne číst hlavičku na řádku začínajícím <code>[REG1TEST</code>.
            V hlavičce bere jen řádky ve tvaru <code>Klíč=hodnota</code>. QSO začne na
            <code>[QSORecords;N]</code>, kde <code>N</code> je deklarovaný počet spojení.
            Počet musí sedět na součet naparsovaných, odmítnutých a ignorovaných řádků.
        </p>

        <pre class="mt-3 overflow-auto rounded bg-surface-2 p-4 text-xs"><code>[REG1TEST;1]
TName=VKV Provozni aktiv
TDate=20260118
PCall=OK1ABC
PWWLo=JN79XX
PBand=144 MHz
PSect=Single
SPowe=50
RName=Jan Novak
RHBBS=ok1abc@example.com
RPhon=+420 777 123 456
[Remarks]
RSoap=Poznamka zavodnika
[QSORecords;2]
260118;0801;OK1AAA;1;59;001;59;001;;JO70AA;3;;;;
260118;0805;OK2BBB;2;599;002;599;010;;JN89AB;4;;;;
[END;]</code></pre>
    </section>

    <section id="hlavicka" class="manual-anchor mt-8">
        <h2>Hlavička REG1TEST</h2>
        <p class="max-w-3xl text-sm text-muted">
            Názvy klíčů jsou pro import citlivé na přesný zápis. Například
            <code>PCall</code> se načte, ale <code>pcall</code> ne. Neznámé klíče parser
            ponechá v surových datech, ale import je nepoužije.
        </p>

        <div class="table-wrap mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pole</th>
                        <th>Nutnost</th>
                        <th>Povolený / očekávaný tvar</th>
                        <th>Vliv na import</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="mono font-bold">TDate</td>
                        <td><x-badge variant="danger">povinné</x-badge></td>
                        <td><code>YYYYMMDD</code>, případně rozsah s oddělovačem, např. <code>20260118;20260118</code>.</td>
                        <td>Určuje kolo podle roku a měsíce. Musí odpovídat dni konání kola a alespoň jednomu dni QSO.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">PCall</td>
                        <td><x-badge variant="danger">povinné</x-badge></td>
                        <td>Neprázdná volací značka. Import ji dál formálně nevaliduje.</td>
                        <td>Prázdná hodnota import zastaví. Slouží pro duplicitu a určení domácí/DX varianty kategorie.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">PWWLo</td>
                        <td><x-badge variant="warn">prakticky povinné</x-badge></td>
                        <td>Platný Maidenhead lokátor 4 nebo 6 znaků, např. <code>JN79</code> nebo <code>JN79XX</code>.</td>
                        <td>Neplatný nebo prázdný domácí lokátor import nezastaví, ale body za QSO vyjdou 0.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">PBand</td>
                        <td><x-badge variant="danger">povinné</x-badge></td>
                        <td><code>144 MHz</code>, <code>145</code>, <code>432 MHz</code>, <code>435</code>, <code>1.3 GHz</code> / <code>1,3 GHz</code>, <code>2.3</code>, <code>3.4</code>, <code>5.7</code>, <code>10</code>, <code>24</code>, <code>47</code>, <code>76</code>, <code>122 GHz</code>.</td>
                        <td>Nerozpoznané pásmo import zastaví, protože nelze určit kategorii.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">PSect</td>
                        <td><x-badge variant="danger">povinné</x-badge></td>
                        <td><code>Single</code>, <code>SO</code> nebo hodnota začínající <code>S</code>; <code>Multi</code> nebo hodnota začínající <code>M</code>.</td>
                        <td>Nerozpoznaná sekce import zastaví. Přípony typu <code>DX</code>, <code>LP</code> nebo <code>HIGH</code> se při určení SO/MO ignorují.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">SPowe</td>
                        <td><x-badge variant="brand">volitelné</x-badge></td>
                        <td>Číselná hodnota ve wattech, s tečkou nebo čárkou jako desetinným oddělovačem, např. <code>50</code>, <code>0.25</code> nebo <code>2,5W</code>.</td>
                        <td>Uloží se do <code>edi_heads.s_powe</code> jako nezáporné desetinné číslo. Prázdné, chybějící, záporné nebo textové hodnoty se uloží jako 0. Z uloženého čísla se odvozují příznaky QRP (&gt;0 až 5 W) a LP (&gt;0 až &lt;100 W).</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">RName</td>
                        <td><x-badge variant="brand">volitelné</x-badge></td>
                        <td>Jméno operátora / stanice.</td>
                        <td>Předvyplní jméno v hlášení a uloží se k výsledkovému řádku.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">RHBBS / REmai</td>
                        <td><x-badge variant="brand">volitelné</x-badge></td>
                        <td>E-mail. Primárně se čte <code>RHBBS</code>, <code>REmai</code> je záloha pro starší deníky.</td>
                        <td>Předvyplní kontaktní e-mail. Ve veřejném formuláři ho závodník před odesláním může upravit.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">RPhon</td>
                        <td><x-badge variant="brand">volitelné</x-badge></td>
                        <td>Telefonní kontakt.</td>
                        <td>Předvyplní telefon v hlášení.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">RSoap</td>
                        <td><x-badge variant="brand">volitelné</x-badge></td>
                        <td>Poznámka / soapbox.</td>
                        <td>Ukládá se do poznámky hlášení, pokud ji závodník nepřepíše.</td>
                    </tr>
                    <tr>
                        <td class="mono font-bold">STXEq, SAnte</td>
                        <td><x-badge variant="brand">volitelné</x-badge></td>
                        <td>Libovolný text.</td>
                        <td>Parser je umí přečíst, aktuální import je neukládá do výsledkového řádku.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="vykon" class="manual-anchor mt-8">
        <h2>Výkon: <code>SPowe</code> → <code>edi_heads.s_powe</code></h2>
        <p class="max-w-3xl text-sm text-muted">
            Původní text řádku <code>SPowe=...</code> zůstává beze změny ve sloupci
            <code>edi_heads.src</code>. Do sloupce <code>edi_heads.s_powe</code> se ukládá
            kladné desetinné číslo ve wattech se čtyřmi desetinnými místy. Import bere
            číselný začátek hodnoty, rozumí tečce i čárce a ignoruje jednotku za číslem.
            Hodnota <code>0.25</code> je proto korektní a uloží se jako <code>0.25</code>.
            Parser hlavičky kvůli hodnotě <code>SPowe</code> import nezastaví; nečíselné
            nebo záporné hodnoty uloží jako 0.
        </p>

        <div class="table-wrap mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hodnota v EDI</th>
                        <th>Uložené <code>s_powe</code></th>
                        <th>Projde?</th>
                        <th>Poznámka</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="mono">SPowe=50</td>
                        <td class="num">50</td>
                        <td><x-badge variant="ok">ano</x-badge></td>
                        <td>Doporučený zápis: celé watty bez jednotky.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=50W<br>SPowe=500 W<br>SPowe=50 Watts</td>
                        <td class="num">50<br>500<br>50</td>
                        <td><x-badge variant="ok">ano</x-badge></td>
                        <td>Jednotka za číslem nevadí, protože převod bere počáteční číslo.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=2,5<br>SPowe=2.5<br>SPowe=8,5<br>SPowe=12.5W</td>
                        <td class="num">2.5<br>2.5<br>8.5<br>12.5</td>
                        <td><x-badge variant="ok">ano</x-badge></td>
                        <td>Desetinná část se ukládá; čárka se normalizuje na tečku.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=0,5<br>SPowe=0.5<br>SPowe=.2<br>SPowe=0.25 W<br>SPowe=0,0005</td>
                        <td class="num">0.5<br>0.5<br>0.2<br>0.25<br>0.0005</td>
                        <td><x-badge variant="ok">ano</x-badge></td>
                        <td>Kladné hodnoty pod 1 W projdou a označí hlášení jako QRP.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=<br>(řádek SPowe chybí)</td>
                        <td class="num">0</td>
                        <td><x-badge variant="ok">ano</x-badge></td>
                        <td>Současný import uloží 0 W. Ve starých datech může být <code>src</code> bez řádku <code>SPowe</code>, ale <code>s_powe</code> vyplněné z původní databáze.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=1kW<br>SPowe=2x400W<br>SPowe=400+300W</td>
                        <td class="num">1<br>2<br>400</td>
                        <td><x-badge variant="warn">ano, ale zavádějící</x-badge></td>
                        <td>Import neumí přepočítat kW ani násobení výkonů; vezme jen počáteční číslo.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=Low<br>SPowe=HIGH<br>SPowe=BEKO-130W<br>SPowe="do 100 W"</td>
                        <td class="num">0<br>0<br>0<br>0</td>
                        <td><x-badge variant="warn">ano, ale jako 0</x-badge></td>
                        <td>Text nezačínající číslem se uloží jako 0 W.</td>
                    </tr>
                    <tr>
                        <td class="mono">SPowe=-5<br>SPowe=70000</td>
                        <td class="num">0<br>70000</td>
                        <td><x-badge variant="warn">část projde</x-badge></td>
                        <td>Záporné hodnoty se berou jako 0. Velká kladná čísla projdou, pokud se vejdou do databázového rozsahu <code>0-99999.9999</code>.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="qso" class="manual-anchor mt-8">
        <h2>QSO záznam - 15 polí</h2>
        <p class="max-w-3xl text-sm text-muted">
            Každý QSO řádek musí mít přesně tento tvar:
            <code>Date;Time;Call;Mode;SentRST;SentNr;RcvdRST;RcvdNr;RcvdExch;RcvdWWL;QSO-Points;NewExch;NewWWL;NewDXCC;Duplicate</code>.
            Parser QSO řádky převádí na velká písmena.
        </p>

        <div class="table-wrap mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Pole</th>
                        <th>Povolený tvar</th>
                        <th>Co se stane při importu</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="num">1</td>
                        <td class="mono font-bold">Date</td>
                        <td><code>RRMMDD</code>, např. <code>260118</code>.</td>
                        <td>Špatná délka, čtyřmístný rok nebo neexistující datum zastaví celý import.</td>
                    </tr>
                    <tr>
                        <td class="num">2</td>
                        <td class="mono font-bold">Time</td>
                        <td>Prázdné nebo <code>HHMM</code> v UTC, např. <code>0801</code>.</td>
                        <td>Špatná délka nebo neexistující čas zastaví import. Prázdný čas projde, ale QSO se nezapočítá.</td>
                    </tr>
                    <tr>
                        <td class="num">3</td>
                        <td class="mono font-bold">Call</td>
                        <td>Písmena, číslice a lomítko: <code>[0-9A-Z/]+</code>.</td>
                        <td>Řádek obsahující <code>ERROR</code> nebo <code>EROR</code> se ignoruje jako vadné spojení.</td>
                    </tr>
                    <tr>
                        <td class="num">4</td>
                        <td class="mono font-bold">Mode</td>
                        <td>Číslo nebo prázdné. Oficiální kódy jsou 1 SSB, 2 CW, 3 SSB/CW, 4 CW/SSB, 5 AM, 6 FM.</td>
                        <td>Jiný kód se uloží jako ostatní režim a neovlivní skóre.</td>
                    </tr>
                    <tr>
                        <td class="num">5</td>
                        <td class="mono font-bold">SentRST</td>
                        <td>Číslice a volitelné jedno písmeno na konci, např. <code>59</code>, <code>599</code>, <code>59A</code>.</td>
                        <td>Písmena <code>A</code>, <code>S</code>, <code>M</code> jsou platná. Jiné písmeno import nezastaví, ale zobrazí varování.</td>
                    </tr>
                    <tr>
                        <td class="num">6</td>
                        <td class="mono font-bold">SentNr</td>
                        <td>Odeslané pořadové číslo, číslice, neprázdné.</td>
                        <td>Prázdné nebo nečíselné pole způsobí, že řádek neprojde vzorem QSO.</td>
                    </tr>
                    <tr>
                        <td class="num">7</td>
                        <td class="mono font-bold">RcvdRST</td>
                        <td>Prázdné nebo stejné jako SentRST.</td>
                        <td>Prázdná hodnota projde importem, ale QSO se nezapočítá jako neúplně přijatý kód.</td>
                    </tr>
                    <tr>
                        <td class="num">8</td>
                        <td class="mono font-bold">RcvdNr</td>
                        <td>Prázdné nebo číslice.</td>
                        <td>Prázdná hodnota projde importem, ale QSO se nezapočítá.</td>
                    </tr>
                    <tr>
                        <td class="num">9</td>
                        <td class="mono font-bold">RcvdExch</td>
                        <td>Prázdné nebo číslice.</td>
                        <td>Ukládá se, ale aktuální bodování ho nepoužívá.</td>
                    </tr>
                    <tr>
                        <td class="num">10</td>
                        <td class="mono font-bold">RcvdWWL</td>
                        <td>Prázdné nebo plný 6znakový Maidenhead <code>[A-R]{2}[0-9]{2}[A-X]{2}</code>, např. <code>JN79AB</code>.</td>
                        <td>Prázdné pole projde, ale QSO se nezapočítá. Neplatný lokátor QSO odmítne, zbytek deníku může pokračovat. Čtyřznakový lokátor v QSO řádku parser nepřijímá.</td>
                    </tr>
                    <tr>
                        <td class="num">11</td>
                        <td class="mono font-bold">QSO-Points</td>
                        <td>Prázdné nebo číslice.</td>
                        <td>Uloží se, ale pro skóre se ignoruje. Body se vždy přepočítají z lokátorů.</td>
                    </tr>
                    <tr>
                        <td class="num">12</td>
                        <td class="mono font-bold">NewExch</td>
                        <td>Prázdné, písmena nebo číslice.</td>
                        <td>Ukládá se pouze jako původní EDI hodnota.</td>
                    </tr>
                    <tr>
                        <td class="num">13</td>
                        <td class="mono font-bold">NewWWL</td>
                        <td>Prázdné, písmena nebo číslice.</td>
                        <td>Ukládá se pouze jako původní EDI hodnota.</td>
                    </tr>
                    <tr>
                        <td class="num">14</td>
                        <td class="mono font-bold">NewDXCC</td>
                        <td>Prázdné, písmena nebo číslice.</td>
                        <td>Ukládá se pouze jako původní EDI hodnota.</td>
                    </tr>
                    <tr>
                        <td class="num">15</td>
                        <td class="mono font-bold">Duplicate</td>
                        <td>Prázdné, písmena nebo číslice. Běžně <code>D</code>.</td>
                        <td>Příznak se uloží a debug ho zvýrazní. Samotný výpočet skóre ho aktuálně nevyřazuje.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="prijeti" class="manual-anchor mt-8">
        <h2>Co import zastaví a co projde</h2>
        <div class="grid gap-4 lg:grid-cols-2">
            <div>
                <h3>Import se odmítne celý</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted">
                    <li>Soubor je prázdný, nečitelný nebo nemá naparsovatelnou EDI strukturu.</li>
                    <li>Některý QSO řádek má jiný počet polí než 15.</li>
                    <li>Vyplněné datum nebo čas QSO není ve formátu <code>RRMMDD</code> / <code>HHMM</code> nebo neexistuje.</li>
                    <li>Deklarovaný počet v <code>[QSORecords;N]</code> nesedí na zpracované řádky.</li>
                    <li>Chybí <code>PCall</code>.</li>
                    <li><code>TDate</code> neukazuje na existující kolo, neodpovídá dni konání kola nebo nesedí s daty QSO.</li>
                    <li>Kolo nepřijímá hlášení. Veřejné nahrání to hlídá, admin ZIP import starších kol upload okno nevynucuje.</li>
                    <li><code>PBand</code> nebo <code>PSect</code> nejde převést na kategorii.</li>
                    <li>Pro stejnou značku, kolo a kategorii už existuje hlášení.</li>
                </ul>
            </div>
            <div>
                <h3>Import projde, ale je potřeba kontrola</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted">
                    <li>QSO označené <code>ERROR</code> / <code>EROR</code> se ignoruje.</li>
                    <li>QSO s neplatným přijatým lokátorem se odmítne, ostatní QSO mohou projít.</li>
                    <li>Prázdný čas, prázdný přijatý RST, prázdné přijaté číslo nebo prázdný lokátor QSO naimportuje, ale nezapočítá.</li>
                    <li>QSO mimo 08:00-11:00 UTC nebo mimo den konání kola se uloží, ale nezapočítá.</li>
                    <li>Neplatné písmeno v reportu je varování, ne důvod k odmítnutí.</li>
                    <li>Neplatný domácí lokátor <code>PWWLo</code> import nezastaví, ale výsledné body budou nulové.</li>
                    <li>Duplicitní značky v QSO se hlásí jako varování; příznak <code>D</code> se zobrazuje v debug rozpadu.</li>
                </ul>
            </div>
        </div>
    </section>

    <section id="skore" class="manual-anchor mt-8">
        <h2>Jak se počítá skóre</h2>
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="card p-4">
                <h3 class="mt-0">Započtené QSO</h3>
                <p class="text-sm text-muted">
                    Musí mít přijatý RST, přijaté pořadové číslo, čas v okně 08:00-11:00 UTC,
                    správný den kola a neprázdný platný velký čtverec protistanice.
                </p>
            </div>
            <div class="card p-4">
                <h3 class="mt-0">Body za QSO</h3>
                <p class="text-sm text-muted">
                    Počítají se z velkých čtverců. Vlastní čtverec = 2 body, sousední
                    čtverec = 3 body, každý další prstenec o bod víc. EDI pole
                    <code>QSO-Points</code> se ignoruje.
                </p>
            </div>
            <div class="card p-4">
                <h3 class="mt-0">Násobič</h3>
                <p class="text-sm text-muted">
                    Počet různých započtených velkých čtverců plus domácí čtverec.
                    Domácí čtverec se počítá vždy, i když s ním není žádné QSO.
                </p>
            </div>
        </div>
        <p class="mt-4 text-sm text-muted">
            Výsledek je <code>součet bodů za započtená QSO x násobič</code>. QSO do vlastního
            velkého čtverce se započítává a má 2 body.
        </p>
    </section>
</article>
@endsection
