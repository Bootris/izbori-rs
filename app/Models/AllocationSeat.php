<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationSeat extends Model
{
    protected $fillable = ['allocation_id', 'electoral_list_id', 'candidate_id', 'seat_no', 'divisor', 'quotient'];

    protected function casts(): array
    {
        return ['quotient' => 'float'];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(ElectoralList::class, 'electoral_list_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
