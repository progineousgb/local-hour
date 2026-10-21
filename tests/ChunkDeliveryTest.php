<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Progineous\LocalHour\LocalHour;
use Progineous\LocalHour\Storage\InMemoryClaimStore;
use Progineous\LocalHour\Tests\Fake\ChunkedUsers;
use RuntimeException;
use Throwable;

final class ChunkDeliveryTest extends LocalHourTestCase
{
    public function test_every_chunk_from_an_eloquent_free_source_is_delivered(): void
    {
        $source = new ChunkedUsers([
            [
                ['id' => 'a', 'timezone' => 'UTC'],
                ['id' => 'b', 'timezone' => 'UTC'],
            ],
            [
                ['id' => 'c', 'timezone' => 'UTC'],
                ['id' => 'd', 'timezone' => 'UTC'],
            ],
            [
                ['id' => 'e', 'timezone' => 'UTC'],
            ],
        ]);

        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->chunkSize(2);
        $localHour->dailyAt('08:00', 'digest')->users($source)->each(static function (array $user) use (&$fired): void {
            $fired[] = (string) $user['id'];
        });

        $report = $localHour->run(new DateTimeImmutable('2026-06-15 08:00:00', new DateTimeZone('UTC')));

        self::assertSame(['a', 'b', 'c', 'd', 'e'], $fired);
        self::assertSame(3, $source->chunksYielded);
        self::assertSame(2, $source->requestedSize);
        self::assertCount(5, $store->all());
        self::assertSame([], $report->skipped());
        self::assertSame([], $report->failed());
    }

    public function test_a_throw_in_an_earlier_chunk_does_not_stop_later_chunks(): void
    {
        $source = new ChunkedUsers([
            [
                ['id' => 'bad', 'timezone' => 'UTC'],
            ],
            [
                ['id' => 'tail', 'timezone' => 'UTC'],
            ],
        ]);

        $fired = [];
        $store = new InMemoryClaimStore();
        $localHour = new LocalHour($store);
        $localHour->chunkSize(1);
        $localHour->setExceptionLogger(static function (Throwable $exception, string $task, string $userId): void {
        });
        $localHour->dailyAt('08:00', 'digest')->users($source)->each(static function (array $user) use (&$fired, $store): void {
            $id = (string) $user['id'];
            if (!$store->contains('digest', $id, '2026-06-15')) {
                throw new RuntimeException('claim was missing for '.$id);
            }
            $fired[] = $id;
            if ($id === 'bad') {
                throw new RuntimeException('smtp down');
            }
        });

        $report = $localHour->run(new DateTimeImmutable('2026-06-15 08:00:00', new DateTimeZone('UTC')));

        self::assertSame(['bad', 'tail'], $fired);
        self::assertSame(2, $source->chunksYielded);
        self::assertSame('smtp down', $report->failed()[0]['exception']->getMessage());
        self::assertTrue($store->contains('digest', 'bad', '2026-06-15'));
        self::assertTrue($store->contains('digest', 'tail', '2026-06-15'));
    }
}
