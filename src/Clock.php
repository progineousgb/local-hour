<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Decides the single UTC minute on which a civil clock time is due.
 *
 * Offsets come from DateTimeZone transitions for the date in question.
 *
 * @internal
 */
final class Clock
{
    /** @var array<string, int|null> */
    private array $cache = [];

    public function flush(): void
    {
        $this->cache = [];
    }

    public function truncateToUtcMinute(DateTimeImmutable $instant): DateTimeImmutable
    {
        $utc = $instant->setTimezone(new DateTimeZone('UTC'));

        return $utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), 0);
    }

    /**
     * Local date of $utcMinute when that minute is the one fire for this clock time.
     * The date is the local date of the instant itself, including a spring-forward delivery.
     */
    public function localDateIfDue(DateTimeImmutable $utcMinute, DateTimeZone $timezone, int $hour, int $minute): ?string
    {
        $local = $utcMinute->setTimezone($timezone);
        $localDate = $local->format('Y-m-d');
        $previousDate = (new DateTimeImmutable($localDate.' 12:00:00', $timezone))
            ->modify('-1 day')
            ->format('Y-m-d');

        $instant = $utcMinute->getTimestamp();
        foreach ([$localDate, $previousDate] as $date) {
            if ($this->canonicalTimestamp($timezone, $date, $hour, $minute) === $instant) {
                return $localDate;
            }
        }

        return null;
    }

    private function canonicalTimestamp(DateTimeZone $timezone, string $localDate, int $hour, int $minute): ?int
    {
        $key = $timezone->getName().'|'.$localDate.'|'.$hour.'|'.$minute;
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        // Treat the civil clock as a UTC timestamp so each transition offset can be applied.
        // Constructing it in the user's zone is wrong: PHP moves a missing spring-forward
        // time forward to a later valid minute, which is not the first minute after the gap.
        $target = (new DateTimeImmutable(
            sprintf('%s %02d:%02d:00', $localDate, $hour, $minute),
            new DateTimeZone('UTC'),
        ))->getTimestamp();

        $begin = $target - 48 * 3600;
        $end = $target + 48 * 3600;
        $transitions = $timezone->getTransitions($begin, $end);
        if (!is_array($transitions) || $transitions === []) {
            // An offset zone such as +03:00, or an abbreviation such as EST, has no
            // transitions. Its offset is fixed, so the clock time maps to one instant.
            return $this->remember($key, $target - $timezone->getOffset(new DateTimeImmutable('@'.$target)));
        }

        $count = count($transitions);
        $matches = [];
        for ($index = 0; $index < $count; $index++) {
            $offset = (int) $transitions[$index]['offset'];
            $start = (int) $transitions[$index]['ts'];
            $segmentEnd = ($index + 1 < $count) ? (int) $transitions[$index + 1]['ts'] : $end + 1;
            $utc = $target - $offset;
            if ($utc >= $start && $utc < $segmentEnd) {
                $matches[] = $utc;
            }
        }

        if ($matches !== []) {
            $matches = array_values(array_unique($matches, SORT_NUMERIC));
            sort($matches, SORT_NUMERIC);

            // A fall-back hour matches twice. The earlier UTC instant is the first occurrence.
            return $this->remember($key, $matches[0]);
        }

        for ($index = 1; $index < $count; $index++) {
            $previousOffset = (int) $transitions[$index - 1]['offset'];
            $nextOffset = (int) $transitions[$index]['offset'];
            if ($nextOffset <= $previousOffset) {
                continue;
            }

            $transition = (int) $transitions[$index]['ts'];
            $gapStart = $transition + $previousOffset;
            $gapEnd = $transition + $nextOffset;
            if ($target >= $gapStart && $target < $gapEnd) {
                return $this->remember($key, $this->firstRealMinute($transition));
            }
        }

        return $this->remember($key, null);
    }

    private function firstRealMinute(int $timestamp): int
    {
        $remainder = $timestamp % 60;
        if ($remainder === 0) {
            return $timestamp;
        }

        if ($remainder > 0) {
            return $timestamp + (60 - $remainder);
        }

        return $timestamp - $remainder;
    }

    private function remember(string $key, ?int $value): ?int
    {
        $this->cache[$key] = $value;

        return $value;
    }
}
