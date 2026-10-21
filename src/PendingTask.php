<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use InvalidArgumentException;
use Progineous\LocalHour\Contracts\UserSource;
use Progineous\LocalHour\Sources\ArrayUserSource;
use Progineous\LocalHour\Sources\EloquentUserSource;

final class PendingTask
{
    /** @var list<array<string, mixed>>|class-string|UserSource|null */
    private array|string|UserSource|null $users = null;

    private string $timezoneColumn = 'timezone';

    /**
     * @param null|array{0: int, 1: int} $fixedTime
     */
    public function __construct(
        private readonly LocalHour $localHour,
        private readonly string $task,
        private readonly ?array $fixedTime,
        private readonly ?string $timeColumn,
    ) {}

    /**
     * @param list<array<string, mixed>>|class-string|UserSource $users an in-memory list, an Eloquent model class, or a chunked source
     */
    public function users(array|string|UserSource $users, string $timezoneColumn = 'timezone'): self
    {
        if (trim($timezoneColumn) === '') {
            throw new InvalidArgumentException('Timezone column name is required.');
        }

        $this->users = $users;
        $this->timezoneColumn = $timezoneColumn;

        return $this;
    }

    public function each(callable $callback): void
    {
        $this->localHour->addTask(new Task(
            $this->task,
            $this->fixedTime,
            $this->timeColumn,
            $this->source(),
            $callback,
        ));
    }

    private function source(): UserSource
    {
        if ($this->users instanceof UserSource) {
            return $this->users;
        }

        if (is_array($this->users)) {
            return new ArrayUserSource($this->users, $this->timezoneColumn, $this->timeColumn);
        }

        if (is_string($this->users)) {
            return new EloquentUserSource($this->users, $this->timezoneColumn, $this->timeColumn);
        }

        throw new InvalidArgumentException('Call users() before each().');
    }
}
