<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel path
    |--------------------------------------------------------------------------
    | URL segment where the Filament admin (data entry + verification) is
    | mounted. Give every deployment a random slug via ADMIN_PATH.
    */

    'admin_path' => env('ADMIN_PATH', 'admin'),

    /*
    | Seeded administrator (change the password right after first login).
    */
    'admin' => [
        'name' => env('SEED_ADMIN_NAME', 'RIK Admin'),
        'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SEED_ADMIN_PASSWORD', 'password'),
    ],

    /*
    | Demo seed stage (database/seeders/Parliamentary2023Seeder):
    |   final    — every protocol verified, allocation = official-style final result
    |   counting — election-night snapshot: missing, unverified and flagged protocols
    */
    'seed_stage' => env('SEED_STAGE', 'final'),

    /*
    |--------------------------------------------------------------------------
    | Snapshots (published JSON)
    |--------------------------------------------------------------------------
    | Every publish writes an immutable folder
    |   {disk}/{election}/{MMDDHHmm}/{source}/...
    | and then atomically switches {disk}/{election}/config.json. The public
    | frontend reads only these files (never the database).
    */

    'snapshots' => [
        'disk' => env('SNAPSHOT_DISK', 'snapshots'),
        // Base URL the SPA prefixes to every data file. Point it at a CDN in production.
        'public_url' => env('SNAPSHOT_PUBLIC_URL', '/data'),
        // How many versions to keep on disk per election+source (0 = keep all).
        'keep_versions' => (int) env('SNAPSHOT_KEEP_VERSIONS', 0),
    ],

    /*
    | Scheduler: how often results are republished while an election is in the
    | "counting" status (minutes).
    */
    'publish_interval_minutes' => (int) env('PUBLISH_INTERVAL_MINUTES', 2),

    /*
    | Prijave sa biračkih mesta: how often the admin bell, the dashboard widget and
    | the incidents table poll for new reports (seconds). This is the longest an
    | alarm can wait to be seen.
    */
    'alarm_poll_seconds' => (int) env('ALARM_POLL_SECONDS', 15),

    /*
    | Turnout cut-off times reported on election day.
    */
    'turnout_cutoffs' => ['07:00', '09:00', '11:00', '13:00', '15:00', '17:00', '19:00'],

    /*
    | A race is "close" when first and second are within this many percentage
    | points, or a list is within this margin of the threshold.
    */
    'close_race_margin_pct' => (float) env('CLOSE_RACE_MARGIN_PCT', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Election type defaults (Serbian electoral law)
    |--------------------------------------------------------------------------
    | Used to prefill a new election in the admin. Everything is editable per
    | election — nothing here is hardcoded into the allocation engine.
    */

    'defaults' => [
        'parliamentary' => ['seats' => 250, 'allocation' => 'dhondt', 'threshold_pct' => 3.0, 'minority_coef' => 1.35, 'rounds' => 1],
        'provincial' => ['seats' => 120, 'allocation' => 'dhondt', 'threshold_pct' => 3.0, 'minority_coef' => 1.35, 'rounds' => 1],
        'local' => ['seats' => null, 'allocation' => 'dhondt', 'threshold_pct' => 3.0, 'minority_coef' => 1.35, 'rounds' => 1],
        'presidential' => ['seats' => null, 'allocation' => 'majority_runoff', 'threshold_pct' => null, 'minority_coef' => null, 'rounds' => 2],
    ],

];
