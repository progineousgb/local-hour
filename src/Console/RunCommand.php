<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Console;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Progineous\LocalHour\LocalHour;

/**
 * Runs every registered task for the current UTC minute.
 * Callback failures are logged and do not fail the command.
 */
class RunCommand extends Command
{
    protected $signature = 'local-hour:run';

    protected $description = 'Run tasks that are due this minute in each user timezone';

    public function handle(LocalHour $localHour): int
    {
        $report = $localHour->run(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        foreach ($report->skipped() as $skip) {
            $this->warn(sprintf(
                'Skipped user %s on task %s (%s): %s',
                $skip['user_id'],
                $skip['task'],
                $skip['timezone'],
                $skip['reason'],
            ));
        }

        return self::SUCCESS;
    }
}
