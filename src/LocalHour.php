<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Progineous\LocalHour\Contracts\ClaimStore;
use Throwable;

class LocalHour
{
    private readonly Clock $clock;

    /** @var list<Task> */
    private array $tasks = [];

    /** @var array<string, true> */
    private array $taskNames = [];

    private int $chunkSize = 1000;

    private ?\Closure $exceptionLogger = null;

    public function __construct(private readonly ClaimStore $claims)
    {
        $this->clock = new Clock();
    }

    public function dailyAt(string $time, string $task): PendingTask
    {
        $parsed = self::parseTime($time);
        if ($parsed === null) {
            throw new InvalidArgumentException('Clock time ['.$time.'] must be HH:MM or HH:MM:SS.');
        }

        return new PendingTask($this, $this->normalizeTaskName($task), $parsed, null);
    }

    public function dailyAtColumn(string $column, string $task): PendingTask
    {
        $column = trim($column);
        if ($column === '') {
            throw new InvalidArgumentException('Time column name is required.');
        }

        return new PendingTask($this, $this->normalizeTaskName($task), null, $column);
    }

    public function chunkSize(int $size): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        $this->chunkSize = $size;

        return $this;
    }

    /**
     * @param callable(Throwable, string, string): void $logger
     */
    public function setExceptionLogger(callable $logger): void
    {
        $this->exceptionLogger = $logger instanceof \Closure ? $logger : \Closure::fromCallable($logger);
    }

    /**
     * @internal
     */
    public function addTask(Task $task): void
    {
        if (isset($this->taskNames[$task->name])) {
            throw new InvalidArgumentException('Task ['.$task->name.'] is already registered.');
        }

        $this->taskNames[$task->name] = true;
        $this->tasks[] = $task;
    }

    public function run(DateTimeImmutable $now): RunReport
    {
        $minute = $this->clock->truncateToUtcMinute($now);
        $this->clock->flush();
        $report = new RunReport();

        foreach ($this->tasks as $task) {
            $task->source->eachChunk($this->chunkSize, function (array $records) use ($task, $minute, $report): void {
                foreach ($records as $record) {
                    if (!$record instanceof UserRecord) {
                        continue;
                    }
                    $this->process($task, $record, $minute, $report);
                }
            });
        }

        return $report;
    }

    private function process(Task $task, UserRecord $record, DateTimeImmutable $minute, RunReport $report): void
    {
        if ($record->id === '') {
            $report->addSkip($task->name, '', $record->timezone, RunReport::MISSING_ID);
            return;
        }

        if ($task->fixedTime !== null) {
            [$hour, $minuteOfHour] = $task->fixedTime;
        } else {
            $parsed = self::parseTime($record->time ?? '');
            if ($parsed === null) {
                $report->addSkip($task->name, $record->id, $record->timezone, RunReport::INVALID_TIME);
                return;
            }
            [$hour, $minuteOfHour] = $parsed;
        }

        $timezoneName = trim($record->timezone);
        if ($timezoneName === '') {
            $report->addSkip($task->name, $record->id, $record->timezone, RunReport::INVALID_TIMEZONE);
            return;
        }

        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (Throwable) {
            $report->addSkip($task->name, $record->id, $timezoneName, RunReport::INVALID_TIMEZONE);
            return;
        }

        $localDate = $this->clock->localDateIfDue($minute, $timezone, $hour, $minuteOfHour);
        if ($localDate === null) {
            return;
        }

        if (!$this->claims->claim($task->name, $record->id, $localDate, $minute)) {
            return;
        }

        try {
            ($task->callback)($record->payload);
        } catch (Throwable $exception) {
            $report->addFailure($task->name, $record->id, $exception);
            $this->logException($exception, $task->name, $record->id);
        }
    }

    private function logException(Throwable $exception, string $task, string $userId): void
    {
        if ($this->exceptionLogger !== null) {
            ($this->exceptionLogger)($exception, $task, $userId);
            return;
        }

        error_log(sprintf(
            'local-hour task %s failed for user %s: %s in %s:%d',
            $task,
            $userId,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));
    }

    private function normalizeTaskName(string $task): string
    {
        $task = trim($task);
        if ($task === '') {
            throw new InvalidArgumentException('Task name is required.');
        }

        return $task;
    }

    /**
     * @return null|array{0: int, 1: int}
     */
    private static function parseTime(string $time): ?array
    {
        $time = trim($time);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = isset($matches[3]) ? (int) $matches[3] : 0;
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return [$hour, $minute];
    }
}
