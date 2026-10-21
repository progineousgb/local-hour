<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Progineous\LocalHour\LocalHour;
use Progineous\LocalHour\RunReport;
use Progineous\LocalHour\Storage\InMemoryClaimStore;

final class DueMinuteTest extends LocalHourTestCase
{
    public function test_a_user_is_due_when_local_hour_and_minute_match(): void
    {
        $newYork = new DateTimeZone('America/New_York');
        $users = [
            ['id' => 7, 'timezone' => 'America/New_York'],
            ['id' => '6b9f0e1a-1c2d-4e5f-8a7b-9c0d1e2f3a4b', 'timezone' => 'UTC'],
        ];

        $eightLocal = new DateTimeImmutable('2026-01-15 08:00:00', $newYork);
        $eightUtc = $eightLocal->setTimezone(new DateTimeZone('UTC'));
        self::assertSame('08:00:00', $eightUtc->setTimezone($newYork)->format('H:i:s'));

        $due = $this->runOnce($users, $eightLocal);
        self::assertSame(['7'], $due['fired']);
        self::assertTrue($due['store']->contains('digest', '7', '2026-01-15'));
        self::assertFalse($due['store']->contains('digest', '6b9f0e1a-1c2d-4e5f-8a7b-9c0d1e2f3a4b', '2026-01-15'));

        $before = $this->runOnce($users, new DateTimeImmutable('2026-01-15 07:59:00', $newYork));
        $after = $this->runOnce($users, new DateTimeImmutable('2026-01-15 08:01:00', $newYork));
        self::assertSame([], $before['fired']);
        self::assertSame([], $after['fired']);
        self::assertSame([], $before['store']->all());
        self::assertSame([], $after['store']->all());

        $utcMorning = new DateTimeImmutable('2026-01-15 08:00:00', new DateTimeZone('UTC'));
        $utcDue = $this->runOnce($users, $utcMorning);
        self::assertSame(['6b9f0e1a-1c2d-4e5f-8a7b-9c0d1e2f3a4b'], $utcDue['fired']);
        self::assertNotSame($eightUtc->getTimestamp(), $utcMorning->getTimestamp());
    }

    public function test_truncating_the_utc_minute_keeps_the_local_minute(): void
    {
        $users = [['id' => 'ny', 'timezone' => 'America/New_York']];
        $late = new DateTimeImmutable('2026-01-15 08:00:59', new DateTimeZone('America/New_York'));
        $onTheMinute = new DateTimeImmutable('2026-01-15 08:00:00', new DateTimeZone('America/New_York'));

        $result = $this->runOnce($users, $late, '08:00:00');
        self::assertSame(['ny'], $result['fired']);
        self::assertSame(
            $onTheMinute->setTimezone(new DateTimeZone('UTC'))->getTimestamp(),
            $result['store']->all()[0]['claimed_at']->getTimestamp(),
        );
        self::assertSame('2026-01-15', $result['store']->all()[0]['local_date']);

        $nextMinute = $this->runOnce($users, new DateTimeImmutable('2026-01-15 08:01:00', new DateTimeZone('America/New_York')));
        self::assertSame([], $nextMinute['fired']);
    }

    public function test_new_york_fall_back_sends_on_the_first_occurrence_only(): void
    {
        $instants = $this->utcMinutesWithLocalClock('America/New_York', '2026-11-01', '01:30');
        self::assertCount(2, $instants);
        self::assertSame('2026-11-01 05:30:00', $instants[0]->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-01 06:30:00', $instants[1]->format('Y-m-d H:i:s'));
        self::assertSame('01:30', $instants[0]->setTimezone(new DateTimeZone('America/New_York'))->format('H:i'));
        self::assertSame('01:30', $instants[1]->setTimezone(new DateTimeZone('America/New_York'))->format('H:i'));
        self::assertLessThan($instants[1]->getTimestamp(), $instants[0]->getTimestamp());

        $users = [['id' => 'ny', 'timezone' => 'America/New_York']];

        $first = $this->runOnce($users, $instants[0], '01:30');
        self::assertSame(['ny'], $first['fired']);
        self::assertTrue($first['store']->contains('digest', 'ny', '2026-11-01'));
        self::assertFalse($first['store']->contains('digest', 'ny', '2026-11-01T05:30:00'));

        $secondOnly = $this->runOnce($users, $instants[1], '01:30');
        self::assertSame([], $secondOnly['fired']);
        self::assertSame([], $secondOnly['store']->all());

        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->dailyAt('01:30', 'digest')->users($users)->each(static function (array $user) use (&$fired): void {
            $fired[] = (string) $user['id'];
        });
        $localHour->run($instants[0]);
        $localHour->run($instants[1]);
        self::assertSame(['ny'], $fired);
        self::assertCount(1, $store->all());
    }

    public function test_new_york_spring_forward_fires_at_the_first_real_minute_after_the_gap(): void
    {
        $zone = new DateTimeZone('America/New_York');
        $missing = $this->utcMinutesWithLocalClock('America/New_York', '2026-03-08', '02:30');
        $landing = $this->utcMinutesWithLocalClock('America/New_York', '2026-03-08', '03:00');
        self::assertSame([], $missing);
        self::assertCount(1, $landing);
        self::assertSame('2026-03-08 07:00:00', $landing[0]->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-08 03:00:00', $landing[0]->setTimezone($zone)->format('Y-m-d H:i:s'));
        self::assertSame('01:59', $landing[0]->modify('-1 minute')->setTimezone($zone)->format('H:i'));

        $begin = (new DateTimeImmutable('2026-03-08 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $end = (new DateTimeImmutable('2026-03-09 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $transitionAt = null;
        $transitions = $zone->getTransitions($begin, $end);
        self::assertIsArray($transitions);
        for ($index = 1, $count = count($transitions); $index < $count; $index++) {
            if ((int) $transitions[$index]['offset'] > (int) $transitions[$index - 1]['offset']) {
                $transitionAt = (int) $transitions[$index]['ts'];
                break;
            }
        }
        self::assertSame($landing[0]->getTimestamp(), $transitionAt);

        $constructed = new DateTimeImmutable('2026-03-08 02:30:00', $zone);
        self::assertNotSame('02:30', $constructed->format('H:i'));

        $users = [['id' => 'ny', 'timezone' => 'America/New_York']];
        $due = $this->runOnce($users, $landing[0], '02:30');
        self::assertSame(['ny'], $due['fired']);
        self::assertSame('2026-03-08', $due['store']->all()[0]['local_date']);
        self::assertSame($landing[0]->getTimestamp(), $due['store']->all()[0]['claimed_at']->getTimestamp());

        self::assertSame([], $this->runOnce($users, $landing[0]->modify('+1 minute'), '02:30')['fired']);
        self::assertSame([], $this->runOnce($users, $landing[0]->modify('-1 minute'), '02:30')['fired']);
        self::assertSame([], $this->runOnce($users, $constructed, '02:30')['fired']);
    }

    public function test_eight_am_on_the_spring_forward_day_fires_only_at_eight_am(): void
    {
        $zone = new DateTimeZone('America/New_York');
        $eight = $this->utcMinutesWithLocalClock('America/New_York', '2026-03-08', '08:00');
        $gapLanding = $this->utcMinutesWithLocalClock('America/New_York', '2026-03-08', '03:00');
        self::assertCount(1, $eight);
        self::assertSame('2026-03-08 12:00:00', $eight[0]->format('Y-m-d H:i:s'));
        self::assertSame('08:00', $eight[0]->setTimezone($zone)->format('H:i'));

        $byZone = (new DateTimeImmutable('2026-03-08 08:00:00', $zone))->setTimezone(new DateTimeZone('UTC'));
        self::assertSame($eight[0]->getTimestamp(), $byZone->getTimestamp());

        $users = [['id' => 'ny', 'timezone' => 'America/New_York']];
        self::assertSame(['ny'], $this->runOnce($users, $eight[0])['fired']);
        self::assertSame([], $this->runOnce($users, $eight[0]->modify('-1 minute'))['fired']);
        self::assertSame([], $this->runOnce($users, $eight[0]->modify('+1 minute'))['fired']);
        self::assertSame([], $this->runOnce($users, $gapLanding[0])['fired']);
    }

    public function test_kolkata_and_kathmandu_fire_at_the_utc_minutes_php_reports(): void
    {
        $kolkata = $this->expectedUtcMinute('Asia/Kolkata', '2026-06-15', '08:00');
        $kathmandu = $this->expectedUtcMinute('Asia/Kathmandu', '2026-06-15', '08:00');
        self::assertSame('2026-06-15 02:30:00', $kolkata->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-15 02:15:00', $kathmandu->format('Y-m-d H:i:s'));

        $users = [
            ['id' => 'kolkata', 'timezone' => 'Asia/Kolkata'],
            ['id' => 'kathmandu', 'timezone' => 'Asia/Kathmandu'],
            ['id' => 'new-york', 'timezone' => 'America/New_York'],
            ['id' => 'cairo', 'timezone' => 'Africa/Cairo'],
        ];

        self::assertSame(['kolkata'], $this->runOnce($users, $kolkata)['fired']);
        self::assertSame(['kathmandu'], $this->runOnce($users, $kathmandu)['fired']);
        self::assertSame([], $this->runOnce($users, $kolkata->modify('-1 minute'))['fired']);
        self::assertSame([], $this->runOnce($users, $kolkata->modify('+1 minute'))['fired']);
        self::assertSame([], $this->runOnce($users, $kathmandu->modify('-1 minute'))['fired']);
        self::assertSame([], $this->runOnce($users, $kathmandu->modify('+1 minute'))['fired']);
    }

    public function test_cairo_fires_at_local_eight_and_not_the_neighbouring_minutes(): void
    {
        $seen = [];
        foreach (['2026-01-15', '2026-07-15'] as $day) {
            $zone = new DateTimeZone('Africa/Cairo');
            $due = $this->expectedUtcMinute('Africa/Cairo', $day, '08:00');
            self::assertSame('08:00:00', $due->setTimezone($zone)->format('H:i:s'));
            $seen[] = $due->format('Y-m-d H:i:s');

            $users = [['id' => 'cairo', 'timezone' => 'Africa/Cairo']];
            self::assertSame(['cairo'], $this->runOnce($users, $due)['fired']);
            self::assertSame([], $this->runOnce($users, new DateTimeImmutable($day.' 07:59:00', $zone))['fired']);
            self::assertSame([], $this->runOnce($users, new DateTimeImmutable($day.' 08:01:00', $zone))['fired']);
        }

        self::assertNotSame($seen[0], $seen[1]);
    }

    public function test_a_missed_minute_is_not_backfilled(): void
    {
        $users = [['id' => 'u', 'timezone' => 'UTC']];
        $late = new DateTimeImmutable('2026-06-15 08:05:00', new DateTimeZone('UTC'));
        $result = $this->runOnce($users, $late);
        self::assertSame([], $result['fired']);
        self::assertSame([], $result['store']->all());
    }

    public function test_daily_at_column_uses_each_users_own_clock_time(): void
    {
        $zone = new DateTimeZone('America/New_York');
        $users = [
            ['id' => 'early', 'timezone' => 'America/New_York', 'digest_at' => '08:00'],
            ['id' => 'late', 'timezone' => 'America/New_York', 'digest_at' => '09:00:00'],
            ['id' => 'bad-time', 'timezone' => 'America/New_York', 'digest_at' => 'morning'],
        ];

        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->dailyAtColumn('digest_at', 'digest')->users($users)->each(static function (array $user) use (&$fired): void {
            $fired[] = (string) $user['id'];
        });

        $atEight = $localHour->run(new DateTimeImmutable('2026-01-15 08:00:00', $zone));
        self::assertSame(['early'], $fired);
        self::assertSame(['bad-time'], array_column($atEight->skipped(), 'user_id'));
        self::assertSame(RunReport::INVALID_TIME, $atEight->skipped()[0]['reason']);
        self::assertFalse($store->contains('digest', 'bad-time', '2026-01-15'));
        self::assertTrue($store->contains('digest', 'early', '2026-01-15'));

        $fired = [];
        $atNine = $localHour->run(new DateTimeImmutable('2026-01-15 09:00:00', $zone));
        self::assertSame(['late'], $fired);
        self::assertSame([], $atNine->failed());
        self::assertTrue($store->contains('digest', 'late', '2026-01-15'));
    }

    public function test_claim_uses_the_local_date_when_it_differs_from_the_utc_date(): void
    {
        $utc = $this->expectedUtcMinute('Asia/Tokyo', '2026-06-15', '08:00');
        self::assertSame('2026-06-14', $utc->format('Y-m-d'));

        $result = $this->runOnce([['id' => 'tokyo', 'timezone' => 'Asia/Tokyo']], $utc);
        self::assertSame(['tokyo'], $result['fired']);
        self::assertTrue($result['store']->contains('digest', 'tokyo', '2026-06-15'));
        self::assertFalse($result['store']->contains('digest', 'tokyo', '2026-06-14'));
    }

    private function expectedUtcMinute(string $timezone, string $localDate, string $hourMinute): DateTimeImmutable
    {
        $found = $this->utcMinutesWithLocalClock($timezone, $localDate, $hourMinute);
        self::assertCount(1, $found, $timezone.' '.$localDate.' '.$hourMinute);

        $zone = new DateTimeZone($timezone);
        [$hour, $minute] = explode(':', $hourMinute);
        $local = new DateTimeImmutable(
            sprintf('%s %02d:%02d:00', $localDate, (int) $hour, (int) $minute),
            $zone,
        );
        $byZone = $local->setTimezone(new DateTimeZone('UTC'));
        $offset = $zone->getTransitions($byZone->getTimestamp(), $byZone->getTimestamp())[0]['offset'];
        $naive = (new DateTimeImmutable(
            sprintf('%s %02d:%02d:00', $localDate, (int) $hour, (int) $minute),
            new DateTimeZone('UTC'),
        ))->getTimestamp();

        self::assertSame($naive - (int) $offset, $byZone->getTimestamp());
        self::assertSame($found[0]->getTimestamp(), $byZone->getTimestamp());

        return $found[0];
    }
}
