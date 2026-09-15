<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Territorial scoping for the admin: an admin sees everything, a verifier or
 * operator only what belongs to their municipality (nothing if unassigned).
 */
final class Access
{
    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function isAdmin(): bool
    {
        return self::user()?->isAdmin() ?? false;
    }

    /** Restrict a query with a `municipality_id` column. */
    public static function scopeMunicipality(Builder $query, string $column = 'municipality_id'): Builder
    {
        $user = self::user();
        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $user->municipality_id
            ? $query->where($column, $user->municipality_id)
            : $query->whereRaw('1 = 0');
    }

    /** Restrict a query through a `pollingStation` relation. */
    public static function scopeThroughStation(Builder $query): Builder
    {
        $user = self::user();
        if ($user === null || $user->isAdmin()) {
            return $query;
        }

        return $user->municipality_id
            ? $query->whereHas('pollingStation', fn (Builder $q) => $q->where('municipality_id', $user->municipality_id))
            : $query->whereRaw('1 = 0');
    }
}
