<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Storage;

use DateTimeImmutable;
use Progineous\LocalHour\Contracts\ClaimStore;

/**
 * Process-local claim set for tests and for running the clock without a database.
 */
final class InMemoryClaimStore implements ClaimStore
{
    /** @var array<string, array{task: string, user_id: string, local_date: string, claimed_at: DateTimeImmutable}> */
    private array $claims = [];

    public function claim(string $task, string $userId, string $localDate, DateTimeImmutable $claimedAt): bool
    {
        $key = $this->key($task, $userId, $localDate);
        if (isset($this->claims[$key])) {
            return false;
        }

        $this->claims[$key] = [
            'task' => $task,
            'user_id' => $userId,
            'local_date' => $localDate,
            'claimed_at' => $claimedAt,
        ];

        return true;
    }

    public function contains(string $task, string $userId, string $localDate): bool
    {
        return isset($this->claims[$this->key($task, $userId, $localDate)]);
    }

    /**
     * @return list<array{task: string, user_id: string, local_date: string, claimed_at: DateTimeImmutable}>
     */
    public function all(): array
    {
        return array_values($this->claims);
    }

    private function key(string $task, string $userId, string $localDate): string
    {
        return $task."\0".$userId."\0".$localDate;
    }
}
