<?php

declare(strict_types=1);

use App\Enums\ElectionStatus;
use App\Enums\SnapshotSource;
use App\Jobs\PublishSnapshotJob;
use App\Models\Election;
use Illuminate\Support\Facades\Schedule;

/*
| Election night: results are republished every N minutes while an election is
| in "counting"; turnout while it is in "voting". Registry is published by hand.
*/
$interval = max(1, (int) config('izbori.publish_interval_minutes'));

Schedule::call(function () {
    Election::query()->where('status', ElectionStatus::Counting)->each(
        fn (Election $e) => PublishSnapshotJob::dispatch($e, SnapshotSource::Results)
    );
    Election::query()->where('status', ElectionStatus::Voting)->each(
        fn (Election $e) => PublishSnapshotJob::dispatch($e, SnapshotSource::Turnout)
    );
})->cron("*/{$interval} * * * *")->name('izbori:auto-publish')->withoutOverlapping();
