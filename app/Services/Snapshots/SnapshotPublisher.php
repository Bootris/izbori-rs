<?php

declare(strict_types=1);

namespace App\Services\Snapshots;

use App\Enums\ElectionStatus;
use App\Enums\SnapshotSource;
use App\Models\Election;
use App\Models\Setting;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Publish pipeline (spec §4, steps 6–8):
 *   1. build every file of the source into {election}/{version}/{source}/…
 *   2. write manifest.json (SHA-256 per file, chained to the previous snapshot)
 *   3. atomically switch {election}/config.json to the new version
 *   4. refresh the root index.json (which elections exist, which is default)
 *
 * A crash before step 3 leaves an orphan folder and an untouched pointer —
 * never a half-published site.
 */
final class SnapshotPublisher
{
    public function __construct(private readonly SnapshotBuilder $builder) {}

    public function publish(Election $election, SnapshotSource $source, ?User $actor = null): Snapshot
    {
        $started = hrtime(true);
        $generatedAt = now();
        $version = $this->uniqueVersion($election, $source, $generatedAt);
        $disk = $this->disk();
        $dir = "{$election->slug}/{$version}/{$source->value}";
        $previous = Snapshot::query()->where('election_id', $election->id)->where('source', $source)->latest('id')->first();

        $hashes = [];
        $bytes = 0;
        foreach ($this->builder->build($election, $source) as $relative => $file) {
            $json = $this->encode($this->envelope($election, $source, $version, $generatedAt, $file));
            $disk->put("{$dir}/{$relative}", $json);
            $hashes[$relative] = hash('sha256', $json);
            $bytes += strlen($json);
        }
        ksort($hashes);

        $chainHash = hash('sha256', ($previous?->hash ?? '').$this->encode($hashes));
        $manifest = $this->encode([
            'election' => $election->slug,
            'source' => $source->value,
            'version' => $version,
            'generated' => $generatedAt->toIso8601String(),
            'previous_hash' => $previous?->hash,
            'hash' => $chainHash,
            'files' => $hashes,
        ]);
        $disk->put("{$dir}/manifest.json", $manifest);

        $snapshot = Snapshot::create([
            'election_id' => $election->id,
            'source' => $source,
            'version' => $version,
            'generated_at' => $generatedAt,
            'path' => $dir,
            'file_count' => count($hashes) + 1,
            'bytes' => $bytes + strlen($manifest),
            'hash' => $chainHash,
            'previous_hash' => $previous?->hash,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1e6),
            'published_by' => $actor?->id,
        ]);

        $this->switchPointer($disk, $election, $source, $version, $generatedAt);
        $this->writeIndex($disk);
        $this->prune($disk, $election, $source);

        Log::info('Snapshot published', ['election' => $election->slug, 'source' => $source->value, 'version' => $version, 'files' => $snapshot->file_count, 'ms' => $snapshot->duration_ms]);

        return $snapshot;
    }

    /** Rewrite index.json only (e.g. after an election's status changed). */
    public function refreshIndex(): void
    {
        $this->writeIndex($this->disk());
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('izbori.snapshots.disk'));
    }

    /**
     * @param array{list?:array<int,mixed>, data?:array<string,mixed>, processed:?float} $file
     * @return array<string, mixed>
     */
    private function envelope(Election $election, SnapshotSource $source, string $version, Carbon $generatedAt, array $file): array
    {
        return [
            'meta' => [
                'generated' => $generatedAt->toIso8601String(),
                'electionDate' => $election->election_date->toDateString(),
                'electionId' => $election->id,
                'electionSlug' => $election->slug,
                'electionType' => $election->type->value,
                'round' => $election->round,
                'source' => $source->value,
                'version' => $version,
                'processed' => $file['processed'],
            ],
            ...array_intersect_key($file, ['list' => true, 'data' => true]),
        ];
    }

    /** MMDDHHmm like the Hungarian VTR; falls back to seconds if published twice in one minute. */
    private function uniqueVersion(Election $election, SnapshotSource $source, Carbon $at): string
    {
        $version = $at->format('mdHi');
        $exists = Snapshot::query()->where('election_id', $election->id)->where('source', $source)->where('version', $version)->exists();

        return $exists ? $at->format('mdHis') : $version;
    }

    private function switchPointer(Filesystem $disk, Election $election, SnapshotSource $source, string $version, Carbon $at): void
    {
        $path = "{$election->slug}/config.json";
        $current = $disk->exists($path) ? (json_decode((string) $disk->get($path), true) ?: []) : [];

        $config = ['election' => $election->slug];
        foreach (SnapshotSource::cases() as $s) {
            $config[$s->value] = $current[$s->value] ?? null;
        }
        $config[$source->value] = $version;
        $config['updated'] = $at->toIso8601String();

        $this->putAtomic($disk, $path, $this->encode($config));
    }

    private function writeIndex(Filesystem $disk): void
    {
        $elections = Election::query()
            ->where('status', '!=', ElectionStatus::Draft)
            ->orderByDesc('election_date')
            ->get()
            ->map(function (Election $e) use ($disk) {
                $path = "{$e->slug}/config.json";
                $config = $disk->exists($path) ? (json_decode((string) $disk->get($path), true) ?: []) : [];

                return [
                    'slug' => $e->slug,
                    'name' => $e->name,
                    'type' => $e->type->value,
                    'election_date' => $e->election_date->toDateString(),
                    'status' => $e->status->value,
                    'round' => $e->round,
                    'sources' => collect(SnapshotSource::cases())
                        ->mapWithKeys(fn (SnapshotSource $s) => [$s->value => $config[$s->value] ?? null])
                        ->all(),
                ];
            })
            ->filter(fn ($e) => $e['sources']['registry'] !== null)
            ->values();

        $settings = Setting::allCached();

        $this->putAtomic($disk, 'index.json', $this->encode([
            'generated' => now()->toIso8601String(),
            'site' => [
                'name' => $settings['site_name'] ?? config('app.name'),
                'publisher' => $settings['publisher'] ?? null,
                'notice' => ($settings['public_notice'] ?? '') ?: null,
                'contact_email' => ($settings['contact_email'] ?? '') ?: null,
                'methodology_url' => ($settings['methodology_url'] ?? '') ?: null,
                // Outbound links of the public "Informacije" hub; each one is hidden while empty.
                'links' => collect(Setting::INFO_LINKS)
                    ->mapWithKeys(fn (string $label, string $key) => [$key => ($settings[$key] ?? '') ?: null])
                    ->all(),
            ],
            'default' => $elections->first()['slug'] ?? null,
            'elections' => $elections->all(),
        ]));
    }

    /** Delete the oldest versions beyond izbori.snapshots.keep_versions (0 = keep everything, the default). */
    private function prune(Filesystem $disk, Election $election, SnapshotSource $source): void
    {
        $keep = (int) config('izbori.snapshots.keep_versions');
        if ($keep <= 0) {
            return;
        }

        Snapshot::query()
            ->where('election_id', $election->id)->where('source', $source)
            ->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->get()
            ->each(function (Snapshot $old) use ($disk) {
                $disk->deleteDirectory($old->path);
                $old->delete();
            });
    }

    /** Write next to the target and rename — readers never see a partial file. */
    private function putAtomic(Filesystem $disk, string $path, string $contents): void
    {
        $tmp = "{$path}.".bin2hex(random_bytes(4)).'.tmp';
        $disk->put($tmp, $contents);
        $disk->delete($path);
        $disk->move($tmp, $path);
    }

    /** @param array<mixed> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
