<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use DateTimeInterface;

final class UserRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $timezone,
        public readonly mixed $payload,
        public readonly ?string $time = null,
    ) {}

    public static function idString(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    public static function timezoneString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    public static function clockString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (is_string($value)) {
            return trim($value);
        }

        return null;
    }
}
