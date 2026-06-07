<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\VkvpaKola;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Generátor iCalendar (RFC 5545) feedu s termíny závodních kol.
 *
 * Každé kolo vytvoří jednu událost na den konání v závodním okně
 * (config vkvpa.contest_window, UTC). Pokud má kolo uzávěrku, přidá se
 * celodenní upomínka na termín odevzdání deníku.
 */
final class IcalFeed
{
    /** Maximální délka řádku v oktetech dle RFC 5545 (kvůli zalamování). */
    private const int LINE_OCTETS = 75;

    /**
     * Sestaví obsah .ics souboru z kolekce kol.
     *
     * @param  iterable<VkvpaKola>  $kola
     */
    public static function build(iterable $kola): string
    {
        $host = (string) (parse_url(Config::string('app.url', ''), PHP_URL_HOST) ?: 'vkvpa');
        $now = Carbon::now('UTC')->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//VKV PA//Kalendar kol//CS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:VKV PA – kola závodu',
            'X-WR-TIMEZONE:UTC',
        ];

        [$fromH, $fromM] = self::parseWindow(ContestWindow::from());
        [$toH, $toM] = self::parseWindow(ContestWindow::to());

        foreach ($kola as $k) {
            $den = $k->datum_konani;
            $start = $den->copy()->setTime($fromH, $fromM)->format('Ymd\THis\Z');
            $end = $den->copy()->setTime($toH, $toM)->format('Ymd\THis\Z');

            $popis = 'VKV Provozní aktiv';
            if ($k->datum_uzaverky instanceof Carbon) {
                $popis .= "\nUzávěrka: ".$k->datum_uzaverky->format('j. n. Y H:i').' UTC';
            }

            array_push(
                $lines,
                'BEGIN:VEVENT',
                'UID:kolo-'.$k->id.'@'.$host,
                'DTSTAMP:'.$now,
                'DTSTART:'.$start,
                'DTEND:'.$end,
                'SUMMARY:'.self::escape((string) $k->nazev),
                'DESCRIPTION:'.self::escape($popis),
                'URL:'.route('kola.index'),
                // Upomínka 2 dny před začátkem závodu.
                'BEGIN:VALARM',
                'ACTION:DISPLAY',
                'DESCRIPTION:'.self::escape('VKV PA – '.((string) $k->nazev).' začíná za 2 dny'),
                'TRIGGER:-P2D',
                'END:VALARM',
                'END:VEVENT',
            );
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * Rozloží okno ve formátu HHMM na [hodina, minuta].
     *
     * @return array{int, int}
     */
    private static function parseWindow(string $hhmm): array
    {
        return [(int) substr($hhmm, 0, 2), (int) substr($hhmm, 2, 2)];
    }

    /** Escapování textové hodnoty dle RFC 5545 (pořadí backslashe je důležité). */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $value,
        );
    }

    /** Zalomení dlouhého řádku (>75 oktetů) na pokračovací řádky s mezerou. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }

        $out = substr($line, 0, self::LINE_OCTETS);
        $rest = substr($line, self::LINE_OCTETS);

        foreach (str_split($rest, self::LINE_OCTETS - 1) as $chunk) {
            $out .= "\r\n ".$chunk;
        }

        return $out;
    }
}
