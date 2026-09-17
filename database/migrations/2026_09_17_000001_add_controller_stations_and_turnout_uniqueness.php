<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Kontrolor biračkog mesta writes only the stations assigned to them here;
| the municipality on the user stays the read scope. One turnout figure per
| station and cut-off: a second entry updates the first instead of doubling
| the municipality sum.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_polling_station', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_station_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'polling_station_id']);
        });

        Schema::table('turnout_snapshots', function (Blueprint $table) {
            $table->unique(['election_id', 'polling_station_id', 'cutoff'], 'turnout_station_cutoff_unique');
        });
    }

    public function down(): void
    {
        Schema::table('turnout_snapshots', function (Blueprint $table) {
            $table->dropUnique('turnout_station_cutoff_unique');
        });
        Schema::dropIfExists('user_polling_station');
    }
};
