<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EdiHead;
use App\Models\EdiRound;
use App\Services\Edi\EdiReducer;
use App\Support\FileName;
use Illuminate\Http\Response;

/**
 * Zobrazení uloženého EDI deníku ve výsledkové listině (původní i redukovaný
 * soubor). Nahrávání nových deníků řeší Livewire komponent App\Livewire\Prihlaska.
 */
class EdiController extends Controller
{
    public function __construct(
        private readonly EdiReducer $reducer,
    ) {}

    /**
     * Zobrazí původní EDI soubor deníku – akce „EDI" ve výsledkové listině.
     *
     * Přístup:
     *   – admin: vždy povolen,
     *   – deník kola s právě otevřeným upload oknem: 403 Omezeno,
     *   – jinak (uzavřená/vyhodnocená kola): povolen i nepřihlášeným.
     */
    public function zobrazit(EdiHead $head): Response
    {
        $this->assertEdiAccess($head);

        return $this->ediResponse($head, (string) $head->src, $head->p_call, 'edi');
    }

    /**
     * Zobrazí REDUKOVANÝ EDI soubor (oříznutý na závodní okno 08:00–11:00 UTC).
     * Stejná přístupová pravidla jako {@see zobrazit()}.
     */
    public function zobrazitRedukovany(EdiHead $head): Response
    {
        $this->assertEdiAccess($head);

        $src = (string) $head->src;
        $reduced = $src === '' ? '' : $this->reducer->reduce($src);

        return $this->ediResponse($head, $reduced, $head->p_call, 'edir');
    }

    /**
     * Admin má vždy přístup. Ostatní (včetně nepřihlášených) jsou blokováni
     * (403) jen u deníku kola, jehož upload okno právě běží – aby během příjmu
     * hlášení neunikaly deníky soupeřů. Deníky uzavřených/vyhodnocených kol
     * zůstávají veřejně přístupné i během okna jiného (probíhajícího) kola.
     */
    private function assertEdiAccess(EdiHead $head): void
    {
        if (auth()->user()?->is_admin) {
            return;
        }

        $kolo = $head->round_id !== null ? EdiRound::find($head->round_id) : null;
        if ($kolo?->acceptsReports() === true) {
            abort(403);
        }
    }

    /**
     * EDI hlavičkové klíče s osobními údaji cizích závodníků – hodnota se ve
     * výpisu nahrazuje za [restricted], aby neunikaly adresy/telefony apod.
     */
    private const REDIGOVANE_KLICE = [
        'PAdr1', 'PAdr2', 'RAdr1', 'RAdr2', 'RPoCo', 'RCity', 'RPhon', 'RHBBS',
    ];

    private function ediResponse(EdiHead $head, string $content, string $pcall, string $variant): Response
    {
        if (trim($content) === '') {
            abort(404, 'EDI soubor není pro tento deník k dispozici.');
        }

        // Admin vidí skutečné hodnoty; ostatním osobní údaje redigujeme.
        if (! auth()->user()?->is_admin) {
            $content = $this->redigovatOsobniUdaje($content);
        }

        $filename = sprintf('%s-%d-%s.edi', FileName::sanitize($pcall), $head->id, $variant);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"; filename*=UTF-8''{$filename}",
        ]);
    }

    /**
     * Nahradí hodnoty osobních hlavičkových klíčů ({@see REDIGOVANE_KLICE})
     * za [restricted] a zachová původní oddělovač řádků (CRLF/LF).
     */
    private function redigovatOsobniUdaje(string $content): string
    {
        $klice = implode('|', self::REDIGOVANE_KLICE);

        return preg_replace(
            '/^('.$klice.')=.*$/mi',
            '$1=[restricted]',
            $content
        ) ?? $content;
    }
}
