<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use Progineous\LocalHour\Contracts\UserSource;

/**
 * @internal
 */
final class Task
{
    /**
     * @param null|array{0: int, 1: int} $fixedTime
     */
    public function __construct(
        public readonly string $name,
        public readonly ?array $fixedTime,
        public readonly ?string $timeColumn,
        public readonly UserSource $source,
        public readonly mixed $callback,
    ) {}
}
