<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Sources;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Progineous\LocalHour\Contracts\UserSource;
use Progineous\LocalHour\UserRecord;

final class EloquentUserSource implements UserSource
{
    public function __construct(
        private readonly string $modelClass,
        private readonly string $timezoneColumn,
        private readonly ?string $timeColumn,
    ) {}

    public function eachChunk(int $size, callable $callback): void
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1.');
        }

        if (!is_a($this->modelClass, Model::class, true)) {
            throw new InvalidArgumentException($this->modelClass.' is not an Eloquent model.');
        }

        /** @var Model $model */
        $model = new $this->modelClass();
        $key = $model->getKeyName();

        // Read whole rows. The callback receives the model, and a notification
        // needs columns such as email that are not the key, timezone, or time.
        $model->newQuery()->chunkById(
            $size,
            function ($models) use ($callback, $key): void {
                $records = [];
                foreach ($models as $row) {
                    if (!$row instanceof Model) {
                        continue;
                    }
                    $records[] = $this->recordFromModel($row, $key);
                }
                if ($records !== []) {
                    $callback($records);
                }
            },
            $key,
        );
    }

    private function recordFromModel(Model $model, string $key): UserRecord
    {
        $time = null;
        if ($this->timeColumn !== null) {
            $time = UserRecord::clockString($model->getAttribute($this->timeColumn));
        }

        return new UserRecord(
            UserRecord::idString($model->getAttribute($key)),
            UserRecord::timezoneString($model->getAttribute($this->timezoneColumn)),
            $model,
            $time,
        );
    }
}
