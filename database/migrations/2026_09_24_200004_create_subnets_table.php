<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subnets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('vlan_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('cidr', 18);
            $table->unsignedBigInteger('network_start');
            $table->unsignedBigInteger('network_end');
            $table->unsignedTinyInteger('prefix');
            $table->string('description')->nullable();
            $table->string('gateway', 15)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'cidr']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subnets');
    }
};
