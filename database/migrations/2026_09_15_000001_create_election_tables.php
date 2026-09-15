<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type');                       // App\Enums\ElectionType
            $table->date('election_date');
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('rounds')->default(1);
            $table->string('allocation');                 // App\Enums\AllocationMethod
            $table->unsignedInteger('seats')->nullable();
            $table->decimal('threshold_pct', 5, 2)->nullable();
            $table->decimal('minority_coef', 6, 3)->nullable();
            $table->string('status')->default('draft');   // App\Enums\ElectionStatus
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('election_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->unsignedInteger('seats')->nullable();  // overrides elections.seats (local elections)
            $table->timestamps();
            $table->unique(['election_id', 'code']);
        });

        Schema::create('election_unit_municipality', function (Blueprint $table) {
            $table->foreignId('election_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->primary(['election_unit_id', 'municipality_id']);
        });

        Schema::create('polling_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('number', 16);
            $table->string('name');
            $table->string('address')->nullable();
            $table->unsignedInteger('registered_voters')->default(0);
            $table->boolean('accessible')->default(false);
            $table->boolean('is_diaspora')->default(false);
            $table->string('country', 64)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->timestamps();
            $table->unique(['election_id', 'municipality_id', 'number']);
        });

        Schema::create('submitters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name', 64)->nullable();
            $table->string('type');                       // App\Enums\SubmitterType
            $table->boolean('is_minority')->default(false);
            $table->string('color', 9)->nullable();
            $table->timestamps();
        });

        Schema::create('electoral_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitter_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('number');       // redni broj na glasačkom listiću
            $table->string('name');
            $table->string('short_name', 64)->nullable();
            $table->string('holder_name')->nullable();    // nosilac liste / kandidat
            $table->boolean('is_minority')->default(false);
            $table->string('color', 9)->nullable();
            $table->timestamps();
            $table->unique(['election_unit_id', 'number']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electoral_list_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('full_name');
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('occupation')->nullable();
            $table->string('residence')->nullable();
            $table->char('gender', 1)->nullable();
            $table->timestamps();
            $table->unique(['electoral_list_id', 'position']);
        });

        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_station_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->string('status')->default('entered'); // App\Enums\ProtocolStatus
            $table->unsignedInteger('registered_voters')->default(0);
            $table->unsignedInteger('ballots_received')->default(0);
            $table->unsignedInteger('ballots_unused')->default(0);
            $table->unsignedInteger('voters_voted')->default(0);
            $table->unsignedInteger('ballots_in_box')->default(0);
            $table->unsignedInteger('ballots_valid')->default(0);
            $table->unsignedInteger('ballots_invalid')->default(0);
            $table->integer('deviation')->default(0);
            $table->json('validation_errors')->nullable();
            $table->boolean('recount_requested')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['polling_station_id', 'round']);
            $table->index(['election_id', 'status']);
            $table->index(['election_unit_id', 'status']);
        });

        Schema::create('protocol_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('electoral_list_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('votes')->default(0);
            $table->timestamps();
            $table->unique(['protocol_id', 'electoral_list_id']);
        });

        Schema::create('protocol_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('protocol_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32);                 // created | updated | verified | annulled
            $table->json('changes')->nullable();          // field => [from, to]
            $table->timestamp('created_at');
        });

        Schema::create('turnout_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polling_station_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('cutoff', 5);                  // "07:00" … "19:00"
            $table->unsignedInteger('voters_voted')->default(0);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['election_id', 'cutoff']);
        });

        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('legal_basis')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->timestamp('computed_at');
            $table->unsignedInteger('total_voted')->default(0);
            $table->unsignedInteger('valid_votes')->default(0);
            $table->unsignedInteger('threshold_votes')->default(0);
            $table->json('result');                       // full allocator output (matrix, ties, winner…)
            $table->timestamps();
            $table->unique(['election_unit_id', 'round']);
        });

        Schema::create('allocation_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('electoral_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('seat_no');
            $table->unsignedSmallInteger('divisor');
            $table->decimal('quotient', 16, 4);
            $table->timestamps();
        });

        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);                 // App\Enums\SnapshotSource
            $table->string('version', 10);                // MMDDHHmm (MMDDHHmmss on same-minute collision)
            $table->timestamp('generated_at');
            $table->string('path');
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('hash', 64);
            $table->string('previous_hash', 64)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['election_id', 'source', 'version']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('operator')->after('password');   // App\Enums\UserRole
            $table->foreignId('municipality_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('settings');
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('allocation_seats');
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('deadlines');
        Schema::dropIfExists('turnout_snapshots');
        Schema::dropIfExists('protocol_revisions');
        Schema::dropIfExists('protocol_scans');
        Schema::dropIfExists('protocol_items');
        Schema::dropIfExists('protocols');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('electoral_lists');
        Schema::dropIfExists('submitters');
        Schema::dropIfExists('polling_stations');
        Schema::dropIfExists('election_unit_municipality');
        Schema::dropIfExists('election_units');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('elections');
    }
};
