# local-hour

local-hour runs one named task at a clock time for each user, in that user's own IANA timezone, once on each local date. The application schedules a single command every minute. Cairo, New York, Kolkata, and Kathmandu then each fire in the minute their own clock shows that time.

## What you get

- `LocalHour::dailyAt('08:00', 'digest')` when every user shares a clock time, and `LocalHour::dailyAtColumn('digest_at', 'digest')` when each user stores their own time.
- An Artisan command, `local-hour:run`, and a service provider that Laravel 12 and 13 discover.
- A `local_hour_runs` table. The unique key is the task name, the user id, and the local date. User ids are strings, so integers and UUIDs both fit. A second run in that same local minute, including an overlapping scheduler process, finds the row and does not call the callback again.
- A plain-PHP runner. Tests construct `Progineous\LocalHour\LocalHour` with `Progineous\LocalHour\Storage\InMemoryClaimStore`, pass `LocalHour::run()` a `DateTimeImmutable`, and pass users as arrays of `id`, `timezone`, and an optional time column. Those tests do not need a User model, a database, or illuminate/support.

## Daylight-saving

The runner truncates the given instant to the minute in UTC, then asks PHP which local time that minute is. It uses the timezone's transitions for that date, not one offset frozen from another day. A user is due when their local hour and minute are the task time.

When a clock falls back, the same local time happens twice. On 1 November 2026 in America/New_York, 01:30 happens twice. The task runs on the first of those two minutes only. The saved key is the task, the user id, and the local date, so the repeated hour cannot send a second time.

When a clock springs forward, the missing local time does not exist. On 8 March 2026 in America/New_York the clock jumps from 02:00 to 03:00, and 02:30 is skipped. A task set for 02:30 runs once at the first real local minute after the gap. That minute is 03:00 Eastern Daylight Time, which is 2026-03-08 07:00:00 UTC. The run is stored with the local date of that instant, 2026-03-08. It does not run again at 03:01. A task set for 08:00 on that same day still runs only at 08:00 local, which is 2026-03-08 12:00:00 UTC.

On 15 June 2026, 08:00 in Asia/Kolkata is 2026-06-15 02:30:00 UTC, and 08:00 in Asia/Kathmandu is 2026-06-15 02:15:00 UTC. Africa/Cairo fires at local 08:00, and not at 07:59 or 08:01, on both the winter offset and the summer offset.

A minute the scheduler misses is a missed day. A later minute does not send it.

## Install

Install from GitHub. The package is not on Packagist.

```bash
composer config repositories.local-hour vcs https://github.com/progineousgb/local-hour
composer require progineousgb/local-hour:dev-main
php artisan vendor:publish --tag=local-hour-migrations
php artisan migrate
```

Laravel 12 and 13 discover the service provider. In `routes/console.php`, schedule one command:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('local-hour:run')->everyMinute();
```

## Example

Register the task while the application boots, for example in `App\Providers\AppServiceProvider::boot`, so `local-hour:run` sees it:

```php
use App\Models\User;
use App\Notifications\DailyDigest;
use Progineous\LocalHour\Facades\LocalHour;

LocalHour::dailyAt('08:00', 'digest')
    ->users(User::class, 'timezone')
    ->each(fn (User $user) => $user->notify(new DailyDigest));
```

`timezone` is an IANA name such as `Africa/Cairo` or `Asia/Kolkata`. The model is read in chunks. The callback receives the user model.

When each user has their own clock time, name that column:

```php
LocalHour::dailyAtColumn('digest_at', 'digest')
    ->users(User::class, 'timezone')
    ->each(fn (User $user) => $user->notify(new DailyDigest));
```

`digest_at` is a time of day, `HH:MM` or `HH:MM:SS`.

## Jobs

The callback should dispatch a job if the work can fail and must be retried. local-hour inserts the claim before it calls the callback. A thrown exception is logged, and the next user still runs.

## Limits

A missed minute is a missed day. local-hour does not backfill it.

The application stores each user's IANA timezone. local-hour does not geolocate IP addresses.

If the callback throws, the claim stays consumed. That task does not run again for that user on that local date.

local-hour is not a replacement for one-off reminders. It decides whether this minute is the local minute for a daily task.

A timezone string PHP does not recognize is skipped and recorded. The other users in that run still go through.
