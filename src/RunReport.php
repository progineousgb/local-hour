<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use Throwable;

final class RunReport
{
    public const INVALID_TIMEZONE = 'invalid_timezone';

    public const INVALID_TIME = 'invalid_time';

    public const MISSING_ID = 'missing_id';

    /** @var list<array{task: string, user_id: string, timezone: string, reason: string}> */
    private array $skipped = [];

    /** @var list<array{task: string, user_id: string, exception: Throwable}> */
    private array $failed = [];

    public function addSkip(string $task, string $userId, string $timezone, string $reason): void
    {
        $this->skipped[] = [
            'task' => $task,
            'user_id' => $userId,
            'timezone' => $timezone,
            'reason' => $reason,
        ];
    }

    public function addFailure(string $task, string $userId, Throwable $exception): void
    {
        $this->failed[] = [
            'task' => $task,
            'user_id' => $userId,
            'exception' => $exception,
        ];
    }

    /**
     * @return list<array{task: string, user_id: string, timezone: string, reason: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * @return list<array{task: string, user_id: string, exception: Throwable}>
     */
    public function failed(): array
    {
        return $this->failed;
    }
}
