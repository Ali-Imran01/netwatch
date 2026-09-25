<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->restrictOnDelete();
            $table->foreignId('circuit_id')->nullable()->constrained()->nullOnDelete(); // copied at open: the monitor may be relinked later
            $table->string('title');
            $table->string('state')->index();
            $table->string('severity')->default('major');
            $table->timestamp('opened_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('provider_ticket')->nullable(); // set when escalated to the carrier
            $table->text('rfo_summary')->nullable();
            $table->text('rfo_root_cause')->nullable();
            $table->text('rfo_corrective_action')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'state']);
        });

        Schema::create('incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null: system or an external ack (Telegram)
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // telegram | email
            $table->string('target'); // Telegram chat id, or an email address
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_channels');
        Schema::dropIfExists('incident_events');
        Schema::dropIfExists('incidents');
    }
};
