<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SnapshotSource;
use App\Models\Election;
use App\Services\Snapshots\SnapshotPublisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** One publish per election+source at a time — overlapping runs would race on config.json. */
class PublishSnapshotJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        public readonly Election $election,
        public readonly SnapshotSource $source,
        public readonly ?int $actorId = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->election->id}:{$this->source->value}";
    }

    public function handle(SnapshotPublisher $publisher): void
    {
        $actor = $this->actorId ? \App\Models\User::find($this->actorId) : null;
        $publisher->publish($this->election, $this->source, $actor);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Snapshot publish failed', [
            'election' => $this->election->slug,
            'source' => $this->source->value,
            'error' => $exception->getMessage(),
        ]);
    }
}
