<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subnet_id')->constrained()->restrictOnDelete();
            $table->string('address', 15);
            $table->unsignedBigInteger('address_long');
            $table->string('status')->default('free');
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dns_name')->nullable();
            $table->timestamps();

            $table->unique(['subnet_id', 'address']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreign('mgmt_ip_id')->references('id')->on('ip_addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', fn (Blueprint $table) => $table->dropForeign(['mgmt_ip_id']));
        Schema::dropIfExists('ip_addresses');
    }
};
