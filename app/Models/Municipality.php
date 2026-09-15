<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = ['district_id', 'code', 'name', 'sort_order'];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function pollingStations(): HasMany
    {
        return $this->hasMany(PollingStation::class)->orderBy('number');
    }

    public function electionUnits(): BelongsToMany
    {
        return $this->belongsToMany(ElectionUnit::class);
    }
}
