<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests\Fake;

use Progineous\LocalHour\Contracts\UserSource;
use Progineous\LocalHour\UserRecord;

/**
 * Eloquent-free source that yields predetermined chunks.
 */
final class ChunkedUsers implements UserSource
{
    public int $chunksYielded = 0;

    public int $requestedSize = 0;

    /**
     * @param list<list<array<string, mixed>>> $chunks
     */
    public function __construct(private readonly array $chunks) {}

    public function eachChunk(int $size, callable $callback): void
    {
        $this->requestedSize = $size;

        foreach ($this->chunks as $rows) {
            $this->chunksYielded++;
            $records = [];
            foreach ($rows as $row) {
                $records[] = new UserRecord(
                    UserRecord::idString($row['id'] ?? null),
                    UserRecord::timezoneString($row['timezone'] ?? null),
                    $row,
                    array_key_exists('digest_at', $row) ? UserRecord::clockString($row['digest_at']) : null,
                );
            }
            $callback($records);
        }
    }
}
