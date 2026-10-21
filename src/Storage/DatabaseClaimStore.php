<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Storage;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\UniqueConstraintViolationException;
use Progineous\LocalHour\Contracts\ClaimStore;

/**
 * Inserts the unique (task, user_id, local_date) row before the callback runs.
 * A duplicate key means this local date was already claimed. The insert is not
 * wrapped around the callback, so a thrown callback cannot roll the claim back.
 */
final class DatabaseClaimStore implements ClaimStore
{
    public function __construct(private readonly Connection $connection) {}

    public function claim(string $task, string $userId, string $localDate, DateTimeImmutable $claimedAt): bool
    {
        try {
            $inserted = $this->connection->table('local_hour_runs')->insert([
                'task' => $task,
                'user_id' => $userId,
                'local_date' => $localDate,
                'claimed_at' => $claimedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($inserted !== true) {
            throw new \RuntimeException('Failed to claim local-hour run.');
        }

        return true;
    }
}
