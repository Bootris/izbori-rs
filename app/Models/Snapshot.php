<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SnapshotSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One immutable published version of one source. Hash-chained to the previous one. */
class Snapshot extends Model
{
    protected $fillable = [
        'election_id', 'source', 'version', 'generated_at', 'path', 'file_count', 'bytes',
        'hash', 'previous_hash', 'duration_ms', 'published_by',
    ];

    protected function casts(): array
    {
        return ['source' => SnapshotSource::class, 'generated_at' => 'datetime'];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
