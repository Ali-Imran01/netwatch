<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            // Flap protection: consecutive failures to go Down, consecutive passes to recover.
            $table->unsignedTinyInteger('down_after')->default(3);
            $table->unsignedTinyInteger('up_after')->default(2);
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->unsignedSmallInteger('consecutive_successes')->default(0);
            $table->timestamp('state_changed_at')->nullable();
        });

        Schema::create('check_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('bucket_start');
            $table->unsignedSmallInteger('bucket_size'); // seconds: 300 or 3600
            $table->unsignedInteger('checks');
            $table->unsignedInteger('failures');
            $table->unsignedInteger('avg_latency_ms')->nullable();
            $table->unsignedInteger('p95_latency_ms')->nullable();

            $table->unique(['monitor_id', 'bucket_size', 'bucket_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_rollups');
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['down_after', 'up_after', 'consecutive_failures', 'consecutive_successes', 'state_changed_at']);
        });
    }
};
