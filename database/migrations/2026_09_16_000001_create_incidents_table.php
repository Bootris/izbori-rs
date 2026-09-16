<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prijave problema sa biračkih mesta: who reported what, when exactly, how serious, and what was done.
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();   // denormalised for Access scoping
            $table->string('category', 32);                 // App\Enums\IncidentCategory
            $table->string('severity', 16);                 // App\Enums\IncidentSeverity
            $table->string('status', 16)->default('open');  // App\Enums\IncidentStatus
            $table->text('description');
            $table->timestamp('occurred_at');               // what the reporter says
            $table->timestamp('reported_at');               // what the server says; never edited
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->boolean('is_public')->default(false);   // shown in incidents.json once the commission decides so
            $table->timestamps();
            $table->index(['election_id', 'status']);
            $table->index(['municipality_id', 'status']);
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
