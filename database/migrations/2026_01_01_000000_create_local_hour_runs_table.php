<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_hour_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('task');
            $table->string('user_id');
            $table->date('local_date');
            $table->timestamp('claimed_at');
            $table->unique(['task', 'user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_hour_runs');
    }
};
