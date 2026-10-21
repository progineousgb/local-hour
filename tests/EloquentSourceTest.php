<?php

declare(strict_types=1);

namespace Progineous\LocalHour\Tests;

use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Progineous\LocalHour\LocalHour;
use Progineous\LocalHour\Storage\DatabaseClaimStore;

final class EloquentSourceUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;
}

final class EloquentSourceTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        if (!class_exists(Capsule::class)) {
            $this->markTestSkipped('illuminate/database is not installed.');
        }

        $this->capsule = new Capsule();
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $schema = $this->capsule->schema();
        $schema->create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('timezone');
        });
        $schema->create('local_hour_runs', static function (Blueprint $table): void {
            $table->id();
            $table->string('task');
            $table->string('user_id');
            $table->date('local_date');
            $table->timestamp('claimed_at');
            $table->unique(['task', 'user_id', 'local_date']);
        });

        EloquentSourceUser::query()->insert([
            ['email' => 'cairo@example.test', 'timezone' => 'Africa/Cairo'],
            ['email' => 'york@example.test', 'timezone' => 'America/New_York'],
        ]);
    }

    public function test_the_callback_receives_the_whole_model_and_runs_once(): void
    {
        $localHour = new LocalHour(new DatabaseClaimStore($this->capsule->getConnection()));
        $emails = [];
        $localHour->dailyAt('08:00', 'digest')
            ->users(EloquentSourceUser::class, 'timezone')
            ->each(static function (EloquentSourceUser $user) use (&$emails): void {
                $emails[] = $user->getAttribute('email');
            });

        // 08:00 in Cairo on 15 June 2026 is 05:00 UTC (EEST, UTC+3).
        $now = new DateTimeImmutable('2026-06-15 05:00:00 UTC');
        $localHour->run($now);
        $localHour->run($now);

        $this->assertSame(['cairo@example.test'], $emails);
        $this->assertSame(1, $this->capsule->table('local_hour_runs')->count());
    }
}
