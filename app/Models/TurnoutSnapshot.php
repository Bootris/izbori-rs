<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voters who had voted by a cut-off time, per municipality (optionally per station). */
class TurnoutSnapshot extends Model
{
    protected $fillable = ['election_id', 'municipality_id', 'polling_station_id', 'cutoff', 'voters_voted', 'entered_by'];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function pollingStation(): BelongsTo
    {
        return $this->belongsTo(PollingStation::class);
    }
}
