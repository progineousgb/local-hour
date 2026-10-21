<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Contracts;

use DateTimeImmutable;

interface ClaimStore
{
    /**
     * Reserve task + user id + local date. False means that local date is already claimed.
     */
    public function claim(string $task, string $userId, string $localDate, DateTimeImmutable $claimedAt): bool;
}
