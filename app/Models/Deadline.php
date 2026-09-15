<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deadline extends Model
{
    protected $fillable = ['election_id', 'date', 'title', 'description', 'legal_basis', 'sort_order'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }
}
