<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Facades;

use Illuminate\Support\Facades\Facade;
use Progineous\LocalHour\LocalHour as LocalHourService;

/**
 * @method static \Progineous\LocalHour\PendingTask dailyAt(string $time, string $task)
 * @method static \Progineous\LocalHour\PendingTask dailyAtColumn(string $column, string $task)
 * @method static \Progineous\LocalHour\RunReport run(\DateTimeImmutable $now)
 */
class LocalHour extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LocalHourService::class;
    }
}
