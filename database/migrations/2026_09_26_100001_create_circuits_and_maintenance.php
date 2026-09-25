<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('noc_email')->nullable();
            $table->string('noc_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('circuits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('a_site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->foreignId('z_site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->string('circuit_ref'); // the provider's own identifier, quoted on tickets
            $table->string('name');
            $table->string('type');
            $table->unsignedInteger('bandwidth_mbps')->nullable();
            $table->decimal('sla_target', 6, 3)->default(99.9);
            $table->timestamps();

            $table->unique(['provider_id', 'circuit_ref']);
        });

        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            // Covers every monitor on a circuit, or a single monitor. Exactly one is set.
            $table->foreignId('circuit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('provider_ref')->nullable(); // provider's change/ticket number
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('circuits');
        Schema::dropIfExists('providers');
    }
};
