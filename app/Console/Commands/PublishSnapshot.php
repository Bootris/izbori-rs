<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SnapshotSource;
use App\Models\Election;
use App\Services\Snapshots\PublishBlockedException;
use App\Services\Snapshots\SnapshotPublisher;
use Illuminate\Console\Command;

class PublishSnapshot extends Command
{
    protected $signature = 'izbori:publish
        {election : Election slug}
        {--source=* : registry | turnout | results | incidents (default: results)}
        {--all : Publish every source, registry first}';

    protected $description = 'Generate an immutable JSON snapshot and switch config.json to it';

    public function handle(SnapshotPublisher $publisher): int
    {
        $election = Election::query()->where('slug', $this->argument('election'))->first();
        if ($election === null) {
            $this->error("Nepoznat izbor: {$this->argument('election')}");

            return self::FAILURE;
        }

        $sources = $this->option('all')
            ? SnapshotSource::cases()
            : array_map(fn (string $s) => SnapshotSource::from($s), $this->option('source') ?: ['results']);

        $failed = false;
        foreach ($sources as $source) {
            try {
                $snapshot = $publisher->publish($election, $source);
            } catch (PublishBlockedException $e) {
                $this->error(sprintf('%-9s → %s', $source->value, $e->getMessage()));
                $failed = true;

                continue;
            }
            $this->info(sprintf('%-9s → %s  (%d fajlova, %s, %d ms)',
                $source->value, $snapshot->version, $snapshot->file_count, $this->bytes($snapshot->bytes), $snapshot->duration_ms));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024).' KB';
    }
}
