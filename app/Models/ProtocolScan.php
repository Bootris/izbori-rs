<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProtocolScan extends Model
{
    protected $fillable = ['protocol_id', 'path', 'original_name', 'uploaded_by'];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
