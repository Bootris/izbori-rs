<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Pages\Concerns;

use App\Models\Protocol;
use App\Services\Protocols\ProtocolService;
use Illuminate\Database\Eloquent\Model;

/**
 * Create and Edit share one path: pull the per-list votes and scan paths out of
 * the form state, hand the numbers to ProtocolService (validation + audit) and
 * sync the scans.
 */
trait PersistsProtocol
{
    /** @param array<string, mixed> $data */
    protected function persist(Protocol $protocol, array $data): Model
    {
        $votes = array_map('intval', (array) ($data['votes'] ?? []));
        $scans = array_values(array_filter((array) ($data['scans'] ?? [])));
        unset($data['votes'], $data['scans']);

        $protocol = app(ProtocolService::class)->save($protocol, $data, $votes, auth()->user());
        $this->syncScans($protocol, $scans);

        return $protocol;
    }

    /** @param array<int, string> $paths */
    private function syncScans(Protocol $protocol, array $paths): void
    {
        $protocol->scans()->whereNotIn('path', $paths)->delete();
        $existing = $protocol->scans()->pluck('path')->all();
        foreach (array_diff($paths, $existing) as $path) {
            $protocol->scans()->create([
                'path' => $path,
                'original_name' => basename($path),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }
}
