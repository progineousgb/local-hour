<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Progineous\LocalHour\LocalHour;
use Progineous\LocalHour\Storage\InMemoryClaimStore;
use RuntimeException;
use Throwable;

final class ClaimTest extends LocalHourTestCase
{
    public function test_invalid_timezone_skips_that_user_and_continues(): void
    {
        $users = [
            ['id' => 'empty', 'timezone' => '   '],
            ['id' => 'bad', 'timezone' => 'Not/AZone'],
            ['id' => 'ok', 'timezone' => 'UTC'],
        ];
        $now = new DateTimeImmutable('2026-06-15 08:00:00', new DateTimeZone('UTC'));
        $result = $this->runOnce($users, $now);

        self::assertSame(['ok'], $result['fired']);
        self::assertSame(['empty', 'bad'], array_column($result['report']->skipped(), 'user_id'));
        self::assertSame(
            ['invalid_timezone', 'invalid_timezone'],
            array_column($result['report']->skipped(), 'reason'),
        );
        self::assertSame('Not/AZone', $result['report']->skipped()[1]['timezone']);
        self::assertTrue($result['store']->contains('digest', 'ok', '2026-06-15'));
        self::assertFalse($result['store']->contains('digest', 'bad', '2026-06-15'));
        self::assertFalse($result['store']->contains('digest', 'empty', '2026-06-15'));
        self::assertSame([], $result['report']->failed());
    }

    public function test_two_runs_in_the_same_local_minute_call_the_callback_once(): void
    {
        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->dailyAt('08:00', 'digest')
            ->users([['id' => 'u', 'timezone' => 'UTC']])
            ->each(static function (array $user) use (&$fired): void {
                $fired[] = (string) $user['id'];
            });

        $now = new DateTimeImmutable('2026-06-15 08:00:00', new DateTimeZone('UTC'));
        $localHour->run($now);
        $localHour->run($now->modify('+59 seconds'));

        self::assertSame(['u'], $fired);
        self::assertCount(1, $store->all());
        self::assertSame($now->getTimestamp(), $store->all()[0]['claimed_at']->getTimestamp());
    }

    public function test_two_task_names_both_fire_for_the_same_user(): void
    {
        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $users = [['id' => 'u', 'timezone' => 'UTC']];
        $now = new DateTimeImmutable('2026-06-15 08:00:00', new DateTimeZone('UTC'));

        foreach (['digest', 'backup'] as $task) {
            $localHour->dailyAt('08:00', $task)->users($users)->each(static function (array $user) use (&$fired, $task): void {
                $fired[] = $task.':'.(string) $user['id'];
            });
        }

        $localHour->run($now);

        self::assertSame(['digest:u', 'backup:u'], $fired);
        self::assertTrue($store->contains('digest', 'u', '2026-06-15'));
        self::assertTrue($store->contains('backup', 'u', '2026-06-15'));
    }

    public function test_claim_is_stored_before_the_callback_and_kept_when_it_throws(): void
    {
        $fired = [];
        $logged = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->setExceptionLogger(static function (Throwable $exception, string $task, string $userId) use (&$logged): void {
            $logged[] = [$task, $userId, $exception->getMessage()];
        });

        $users = [
            ['id' => 'bad', 'timezone' => 'UTC'],
            ['id' => 'good', 'timezone' => 'UTC'],
        ];
        $now = new DateTimeImmutable('2026-06-15 08:00:00', new DateTimeZone('UTC'));

        $localHour->dailyAt('08:00', 'digest')->users($users)->each(static function (array $user) use (&$fired, $store): void {
            $id = (string) $user['id'];
            if (!$store->contains('digest', $id, '2026-06-15')) {
                throw new RuntimeException('claim was missing for '.$id);
            }
            $fired[] = $id;
            if ($id === 'bad') {
                throw new RuntimeException('smtp down');
            }
        });

        $report = $localHour->run($now);

        self::assertSame(['bad', 'good'], $fired);
        self::assertTrue($store->contains('digest', 'bad', '2026-06-15'));
        self::assertTrue($store->contains('digest', 'good', '2026-06-15'));
        self::assertSame('smtp down', $report->failed()[0]['exception']->getMessage());
        self::assertSame([['digest', 'bad', 'smtp down']], $logged);
        self::assertSame([], $report->skipped());

        $localHour->run($now);
        self::assertSame(['bad', 'good'], $fired);
        self::assertCount(1, $logged);
        self::assertCount(2, $store->all());
    }

    public function test_bad_registration_is_rejected_before_run(): void
    {
        $localHour = new LocalHour(new InMemoryClaimStore());
        $rejected = 0;

        try {
            $localHour->dailyAt('25:00', 'digest');
        } catch (InvalidArgumentException) {
            $rejected++;
        }

        try {
            $localHour->dailyAt('08:00', 'digest')->each(static function (): void {});
        } catch (InvalidArgumentException) {
            $rejected++;
        }

        $localHour->dailyAt('08:00', 'digest')->users([])->each(static function (): void {});

        try {
            $localHour->dailyAt('08:00', 'digest')->users([])->each(static function (): void {});
        } catch (InvalidArgumentException) {
            $rejected++;
        }

        self::assertSame(3, $rejected);
    }
}
