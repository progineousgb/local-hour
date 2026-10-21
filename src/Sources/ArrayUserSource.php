<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Sources;

use InvalidArgumentException;
use Progineous\LocalHour\Contracts\UserSource;
use Progineous\LocalHour\UserRecord;

final class ArrayUserSource implements UserSource
{
    /**
     * @param list<array<string, mixed>> $users
     */
    public function __construct(
        private readonly array $users,
        private readonly string $timezoneColumn,
        private readonly ?string $timeColumn,
    ) {}

    public function eachChunk(int $size, callable $callback): void
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $records = [];
        foreach ($this->users as $index => $user) {
            if (!is_array($user)) {
                throw new InvalidArgumentException('In-memory user at index '.$index.' must be an array.');
            }

            $id = UserRecord::idString($user['id'] ?? null);
            if ($id === '') {
                throw new InvalidArgumentException('In-memory user at index '.$index.' is missing an id.');
            }

            $time = null;
            if ($this->timeColumn !== null && array_key_exists($this->timeColumn, $user)) {
                $time = UserRecord::clockString($user[$this->timeColumn]);
            }

            $records[] = new UserRecord(
                $id,
                UserRecord::timezoneString($user[$this->timezoneColumn] ?? null),
                $user,
                $time,
            );
        }

        foreach (array_chunk($records, $size) as $chunk) {
            $callback($chunk);
        }
    }
}
