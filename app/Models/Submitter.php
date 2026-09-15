<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmitterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Podnosilac izborne liste: stranka, koalicija ili grupa građana. */
class Submitter extends Model
{
    protected $fillable = ['election_id', 'name', 'short_name', 'type', 'is_minority', 'color'];

    protected function casts(): array
    {
        return [
            'type' => SubmitterType::class,
            'is_minority' => 'boolean',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ElectoralList::class);
    }
}
