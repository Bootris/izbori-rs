<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolItem extends Model
{
    protected $fillable = ['protocol_id', 'electoral_list_id', 'votes'];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(ElectoralList::class, 'electoral_list_id');
    }
}
