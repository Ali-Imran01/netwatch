<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            // FK to ip_addresses is added in the ip_addresses migration (circular reference).
            $table->unsignedBigInteger('mgmt_ip_id')->nullable();
            $table->string('serial')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
