<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('severity')->constrained('users')->nullOnDelete(); // the engineer who owns it
        });

        Schema::table('incident_events', function (Blueprint $table) {
            $table->string('type')->default('state')->after('incident_id'); // state | note | assignment; the latter two keep to_state as the state at the time
        });
    }

    public function down(): void
    {
        Schema::table('incident_events', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::table('incidents', fn (Blueprint $table) => $table->dropConstrainedForeignId('assignee_id'));
    }
};
