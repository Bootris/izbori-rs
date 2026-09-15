<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = ['code', 'name', 'sort_order'];

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class)->orderBy('sort_order')->orderBy('name');
    }
}
