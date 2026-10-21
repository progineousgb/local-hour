# Changelog

## v1.0.0 (2026-10-22)

First tagged release. Run a task at the same local clock time for each user, once, including daylight-saving days.

- Run each user's daily task at their own local clock time.
- Fix: the Eloquent source read only the key, timezone, and time columns, so the callback got a partial model and a mail notification had no `email`. It now reads whole rows.
- Fix: a fixed-offset timezone such as `+03:00`, or an abbreviation such as `EST`, never fired and was not reported. It now fires at its fixed offset.
- Fix: the service provider used the `database_path()` helper. It now uses the application's `databasePath()`.
- Tests for Cairo, Lord Howe, and Samoa calendar changes, offset zones, and the Eloquent source with SQLite.
- PHPStan level 6, a CI workflow for PHP 8.2 to 8.5, and setup steps for Linux, macOS, and Windows.
