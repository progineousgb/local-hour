<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Progineous\LocalHour\LocalHour;
use Progineous\LocalHour\RunReport;
use Progineous\LocalHour\Storage\InMemoryClaimStore;

abstract class LocalHourTestCase extends TestCase
{
    /**
     * UTC minutes on $localDate whose clock reads $hourMinute, found by converting each minute.
     *
     * @return list<DateTimeImmutable>
     */
    protected function utcMinutesWithLocalClock(string $timezone, string $localDate, string $hourMinute): array
    {
        $zone = new DateTimeZone($timezone);
        $start = (new DateTimeImmutable($localDate.' 00:00:00', new DateTimeZone('UTC')))->modify('-14 hours');
        $found = [];

        for ($step = 0; $step < 40 * 60; $step++) {
            $instant = $start->modify('+'.$step.' minutes');
            $local = $instant->setTimezone($zone);
            if ($local->format('Y-m-d H:i') === $localDate.' '.$hourMinute) {
                $found[] = $instant;
            }
        }

        return $found;
    }

    /**
     * @param list<array<string, mixed>> $users
     * @return array{fired: list<string>, store: InMemoryClaimStore, report: RunReport}
     */
    protected function runOnce(array $users, DateTimeImmutable $now, string $time = '08:00', string $task = 'digest'): array
    {
        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->dailyAt($time, $task)->users($users)->each(static function (array $user) use (&$fired): void {
            $fired[] = (string) $user['id'];
        });
        $report = $localHour->run($now);

        return [
            'fired' => $fired,
            'store' => $store,
            'report' => $report,
        ];
    }
}
