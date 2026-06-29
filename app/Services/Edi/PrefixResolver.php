<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\Prefix;

/**
 * Přiřazení volací značky k zemi (DXCC) a prefixu z číselníku `edi_prefixes`.
 *
 * Hledá nejdelší prefix z tabulky, kterým značka začíná (longest-match) –
 * tak 3znakové prefixy (HB9 vs HB0, OH0 vs OH, UA2 Kaliningrad vs UA1 evropské
 * Rusko) vyhrají nad kratšími. Pro porovnání se bere část značky před prvním
 * lomítkem, takže „OK1ABC/P" → OK a hostující „9A/OK1ABC" → 9A (Chorvatsko).
 *
 * Konstruktor je bez DB (snadno testovatelný); z databáze se naplní továrnou
 * {@see fromDatabase()}.
 */
final class PrefixResolver
{
    /** @var list<array{prefix: string, country: string}> seřazeno sestupně podle délky prefixu */
    private array $map;

    /**
     * @param  iterable<array{prefix: string, country: string}>  $rows
     */
    public function __construct(iterable $rows)
    {
        $map = [];

        foreach ($rows as $r) {
            $prefix = strtoupper(trim($r['prefix']));
            if ($prefix !== '') {
                $map[] = ['prefix' => $prefix, 'country' => $r['country']];
            }
        }

        // Delší prefixy první → str_starts_with() vrátí nejspecifičtější shodu.
        usort($map, fn (array $a, array $b): int => strlen($b['prefix']) <=> strlen($a['prefix']));

        $this->map = $map;
    }

    public static function fromDatabase(): self
    {
        return new self(
            Prefix::query()
                ->get(['prefix', 'country'])
                ->map(fn (Prefix $p): array => [
                    'prefix' => (string) $p->prefix,
                    'country' => (string) $p->country,
                ])
                ->all(),
        );
    }

    /**
     * Prefix a země značky, nebo null když žádný prefix nesedí.
     *
     * @return array{prefix: string, country: string}|null
     */
    public function lookup(string $call): ?array
    {
        $c = self::callPrefixPart($call);
        if ($c === '') {
            return null;
        }

        foreach ($this->map as $entry) {
            if (str_starts_with($c, $entry['prefix'])) {
                return $entry;
            }
        }

        return null;
    }

    /** Část značky rozhodná pro prefix: velká písmena, před prvním lomítkem. */
    private static function callPrefixPart(string $call): string
    {
        $call = strtoupper(trim($call));
        $slash = strpos($call, '/');

        return $slash === false ? $call : substr($call, 0, $slash);
    }
}
