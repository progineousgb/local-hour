<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PackageMetadataTest extends TestCase
{
    public function test_package_contract_files(): void
    {
        $root = dirname(__DIR__);
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('progineousgb/local-hour', $composer['name']);
        self::assertSame('MIT', $composer['license']);
        self::assertSame('>=8.2', $composer['require']['php']);
        self::assertArrayNotHasKey('illuminate/support', $composer['require']);
        self::assertArrayHasKey('illuminate/support', $composer['suggest']);
        self::assertSame('src/', $composer['autoload']['psr-4']['Progineous\\LocalHour\\']);
        self::assertSame(
            'Progineous\\LocalHour\\LocalHourServiceProvider',
            $composer['extra']['laravel']['providers'][0],
        );

        $migration = (string) file_get_contents($root.'/database/migrations/2026_01_01_000000_create_local_hour_runs_table.php');
        self::assertStringContainsString("'local_hour_runs'", $migration);
        self::assertStringContainsString("string('task')", $migration);
        self::assertStringContainsString("string('user_id')", $migration);
        self::assertStringContainsString("date('local_date')", $migration);
        self::assertStringContainsString("timestamp('claimed_at')", $migration);
        self::assertStringContainsString("unique(['task', 'user_id', 'local_date'])", $migration);
        self::assertStringNotContainsString('user_type', $migration);

        $provider = (string) file_get_contents($root.'/src/LocalHourServiceProvider.php');
        self::assertStringContainsString('local-hour-migrations', $provider);
        self::assertStringContainsString('2026_01_01_000000_create_local_hour_runs_table.php', $provider);

        $command = (string) file_get_contents($root.'/src/Console/RunCommand.php');
        self::assertStringContainsString("protected \$signature = 'local-hour:run';", $command);

        $readme = (string) file_get_contents($root.'/README.md');
        self::assertStringContainsString('https://github.com/progineousgb/local-hour', $readme);
        self::assertStringContainsString('dev-main', $readme);
        self::assertStringContainsString("Schedule::command('local-hour:run')->everyMinute();", $readme);
        self::assertStringContainsString('2026-03-08 07:00:00 UTC', $readme);
        self::assertStringContainsString('2026-03-08 12:00:00 UTC', $readme);
        self::assertStringContainsString('2026-06-15 02:30:00 UTC', $readme);
        self::assertStringContainsString("LocalHour::dailyAt('08:00', 'digest')", $readme);
        self::assertStringContainsString('dispatch a job', $readme);

        $license = (string) file_get_contents($root.'/LICENSE');
        self::assertStringContainsString('MIT License', $license);
        self::assertStringContainsString('Copyright (c) 2026 Pro Gineous', $license);
    }

    public function test_php_files_declare_strict_types(): void
    {
        $root = dirname(__DIR__);
        $paths = [
            $root.'/src',
            $root.'/database',
            $root.'/tests',
        ];

        $checked = 0;
        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $contents = (string) file_get_contents($file->getPathname());
                self::assertStringContainsString('declare(strict_types=1);', $contents, $file->getPathname());
                $checked++;
            }
        }

        self::assertGreaterThan(10, $checked);
    }
}
