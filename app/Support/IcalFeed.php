<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\VkvpaKola;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Alarm;
use Eluceo\iCal\Domain\ValueObject\Alarm\DisplayAction;
use Eluceo\iCal\Domain\ValueObject\Alarm\RelativeTrigger;
use Eluceo\iCal\Domain\ValueObject\DateTime as IcalDateTime;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Illuminate\Support\Carbon;

/**
 * Generátor iCalendar feedu s termíny závodních kol.
 */
final class IcalFeed
{
    /**
     * @param  iterable<VkvpaKola>  $kola
     */
    public static function build(iterable $kola): string
    {
        $calendar = (new Calendar)
            ->setProductIdentifier('-//VKV PA//Kalendar kol//CS');

        [$fromH, $fromM] = self::parseWindow(ContestWindow::from());
        [$toH, $toM] = self::parseWindow(ContestWindow::to());

        $twoDaysBefore = new DateInterval('P2D');
        $twoDaysBefore->invert = 1;

        foreach ($kola as $k) {
            $date = $k->datum_konani->format('Y-m-d');
            $nazev = (string) $k->nazev;

            $dtStart = new DateTimeImmutable(
                sprintf('%s %02d:%02d:00', $date, $fromH, $fromM),
                new DateTimeZone('UTC'),
            );
            $dtEnd = new DateTimeImmutable(
                sprintf('%s %02d:%02d:00', $date, $toH, $toM),
                new DateTimeZone('UTC'),
            );

            $popis = 'VKV Provozní aktiv';
            if ($k->datum_uzaverky instanceof Carbon) {
                $popis .= "\nUzávěrka: ".$k->datum_uzaverky->format('j. n. Y H:i').' UTC';
            }

            $event = (new Event(new UniqueIdentifier('kolo-'.$k->id)))
                ->setSummary($nazev)
                ->setDescription($popis)
                ->setUrl(new Uri(route('kola.index')))
                ->setOccurrence(new TimeSpan(
                    new IcalDateTime($dtStart, true),
                    new IcalDateTime($dtEnd, true),
                ))
                ->addAlarm(new Alarm(
                    new DisplayAction('VKV PA – '.$nazev.' začíná za 2 dny'),
                    new RelativeTrigger($twoDaysBefore),
                ));

            $calendar->addEvent($event);
        }

        return (string) (new CalendarFactory)->createCalendar($calendar);
    }

    /**
     * @return array{int, int}
     */
    private static function parseWindow(string $hhmm): array
    {
        return [(int) substr($hhmm, 0, 2), (int) substr($hhmm, 2, 2)];
    }
}
