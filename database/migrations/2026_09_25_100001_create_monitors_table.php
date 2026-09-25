<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('target');
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedInteger('interval_s')->default(60);
            $table->unsignedInteger('timeout_ms')->default(3000);
            // Polymorphic so Week 5 can attach circuits; only devices for now.
            $table->nullableMorphs('monitorable');
            $table->boolean('enabled')->default(true);
            $table->string('state')->default('unknown'); // driven by the Week 4 evaluator
            $table->timestamp('next_check_at')->nullable()->index();
            // Denormalised latest result so the list needs no join per row.
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('last_success')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('success');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('detail', 500)->nullable();

            $table->index(['monitor_id', 'checked_at']);
            $table->index('checked_at'); // prune scans by age alone
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_results');
        Schema::dropIfExists('monitors');
    }
};
