<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    protected $fillable = ['electoral_list_id', 'position', 'full_name', 'birth_year', 'occupation', 'residence', 'gender'];

    public function list(): BelongsTo
    {
        return $this->belongsTo(ElectoralList::class, 'electoral_list_id');
    }
}
