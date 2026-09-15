<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line on the ballot. For proportional elections it carries an ordered
 * candidate list; for presidential elections it is the candidate (one row).
 */
class ElectoralList extends Model
{
    protected $fillable = [
        'election_id', 'election_unit_id', 'submitter_id', 'number', 'name', 'short_name',
        'holder_name', 'is_minority', 'color',
    ];

    protected function casts(): array
    {
        return ['is_minority' => 'boolean'];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ElectionUnit::class, 'election_unit_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Submitter::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->orderBy('position');
    }

    public function protocolItems(): HasMany
    {
        return $this->hasMany(ProtocolItem::class);
    }
}
