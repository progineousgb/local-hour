<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use DateTimeImmutable;
use Progineous\LocalHour\LocalHour;
use Progineous\LocalHour\Storage\InMemoryClaimStore;

final class EdgeCaseTest extends LocalHourTestCase
{
    /**
     * @return list<string>
     */
    private function firesBetween(string $timezone, string $time, string $fromUtc, string $toUtc): array
    {
        $localHour = new LocalHour(new InMemoryClaimStore());
        $hits = [];
        $current = '';
        $localHour->dailyAt($time, 'task')
            ->users([['id' => 1, 'timezone' => $timezone]])
            ->each(static function () use (&$hits, &$current): void {
                $hits[] = $current;
            });

        $end = (new DateTimeImmutable($toUtc.' UTC'))->getTimestamp();
        for ($t = (new DateTimeImmutable($fromUtc.' UTC'))->getTimestamp(); $t <= $end; $t += 60) {
            $current = gmdate('Y-m-d H:i', $t);
            $localHour->run(new DateTimeImmutable('@'.$t));
        }

        return $hits;
    }

    public function test_an_offset_timezone_fires_once_a_day(): void
    {
        $this->assertSame(['2026-06-14 05:00'], $this->firesBetween('+03:00', '08:00', '2026-06-14 00:00', '2026-06-14 23:59'));
    }

    public function test_an_abbreviation_timezone_fires_once_a_day(): void
    {
        $this->assertSame(['2026-06-14 13:00'], $this->firesBetween('EST', '08:00', '2026-06-14 00:00', '2026-06-14 23:59'));
    }

    public function test_cairo_spring_forward_gap_at_midnight(): void
    {
        // Egypt moves from 00:00 to 01:00 on Friday 24 April 2026.
        $this->assertSame(
            ['2026-04-23 22:00', '2026-04-24 21:30'],
            $this->firesBetween('Africa/Cairo', '00:30', '2026-04-23 18:00', '2026-04-25 06:00'),
        );
    }

    public function test_cairo_fall_back_runs_on_the_first_occurrence(): void
    {
        // Egypt moves from 24:00 back to 23:00 on Thursday 29 October 2026.
        $this->assertSame(
            ['2026-10-28 20:30', '2026-10-29 20:30', '2026-10-30 21:30'],
            $this->firesBetween('Africa/Cairo', '23:30', '2026-10-28 18:00', '2026-10-31 06:00'),
        );
    }

    public function test_lord_howe_half_hour_gap(): void
    {
        // Lord Howe Island moves from 02:00 to 02:30 on 4 October 2026.
        $this->assertSame(
            ['2026-10-02 15:45', '2026-10-03 15:30', '2026-10-04 15:15'],
            $this->firesBetween('Australia/Lord_Howe', '02:15', '2026-10-02 00:00', '2026-10-05 00:00'),
        );
    }

    public function test_a_skipped_calendar_day_does_not_double_fire(): void
    {
        // Samoa skipped 30 December 2011.
        $hits = $this->firesBetween('Pacific/Apia', '08:00', '2011-12-28 00:00', '2012-01-01 00:00');
        $this->assertSame(['2011-12-28 18:00', '2011-12-29 18:00', '2011-12-30 18:00', '2011-12-31 18:00'], $hits);
    }
}
