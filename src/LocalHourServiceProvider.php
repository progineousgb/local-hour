<?php

declare(strict_types=1);

namespace Progineous\LocalHour;

use Illuminate\Support\ServiceProvider;
use Progineous\LocalHour\Console\RunCommand;
use Progineous\LocalHour\Storage\DatabaseClaimStore;
use Throwable;

class LocalHourServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalHour::class, function ($app): LocalHour {
            $localHour = new LocalHour(new DatabaseClaimStore($app['db']->connection()));

            if ($app->bound('log')) {
                $log = $app->make('log');
                $localHour->setExceptionLogger(static function (Throwable $exception, string $task, string $userId) use ($log): void {
                    $log->error('local-hour callback failed', [
                        'task' => $task,
                        'user_id' => $userId,
                        'exception' => $exception,
                    ]);
                });
            }

            return $localHour;
        });

        $this->app->alias(LocalHour::class, 'local-hour');
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            RunCommand::class,
        ]);

        $migration = dirname(__DIR__).'/database/migrations/2026_01_01_000000_create_local_hour_runs_table.php';
        $this->publishes([
            $migration => $this->app->databasePath('migrations/2026_01_01_000000_create_local_hour_runs_table.php'),
        ], 'local-hour-migrations');
    }
}
