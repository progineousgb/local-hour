<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Contracts;

use Progineous\LocalHour\UserRecord;

interface UserSource
{
    /**
     * @param callable(list<UserRecord>): void $callback
     */
    public function eachChunk(int $size, callable $callback): void;
}
