<?php

declare(strict_types=1);

use App\Enums\ElectionStatus;
use App\Enums\SnapshotSource;
use App\Jobs\PublishSnapshotJob;
use App\Models\Election;
use App\Models\Incident;
use App\Models\Snapshot;
use Illuminate\Support\Facades\Schedule;

/*
| Election night: results are republished every N minutes while an election is
| in "counting" and the polls have closed (Election::resultsPublishable);
| turnout while it is in "voting". Registry is published by hand.
| Incident reports follow both phases, but only when a report changed since the
| last incidents snapshot: an empty day must not pile up hundreds of versions.
*/
$interval = max(1, (int) config('izbori.publish_interval_minutes'));

Schedule::call(function () {
    Election::query()->where('status', ElectionStatus::Counting)->get()
        ->filter(fn (Election $e) => $e->resultsPublishable())
        ->each(fn (Election $e) => PublishSnapshotJob::dispatch($e, SnapshotSource::Results));
    Election::query()->where('status', ElectionStatus::Voting)->each(
        fn (Election $e) => PublishSnapshotJob::dispatch($e, SnapshotSource::Turnout)
    );
    Election::query()->whereIn('status', [ElectionStatus::Voting, ElectionStatus::Counting])->each(function (Election $e) {
        $last = Snapshot::query()->where('election_id', $e->id)->where('source', SnapshotSource::Incidents)->latest('id')->value('generated_at');
        $changed = Incident::query()->where('election_id', $e->id)->when($last, fn ($q) => $q->where('updated_at', '>', $last))->exists();
        if ($changed) {
            PublishSnapshotJob::dispatch($e, SnapshotSource::Incidents);
        }
    });
})->cron("*/{$interval} * * * *")->name('izbori:auto-publish')->withoutOverlapping();
