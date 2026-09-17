<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'municipality_id'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<int, int>|null memoised per request: a policy asks per table row */
    private ?array $stationIds = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** Stations a controller is allowed to write; empty for every other role. */
    public function pollingStations(): BelongsToMany
    {
        return $this->belongsToMany(PollingStation::class, 'user_polling_station');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isController(): bool
    {
        return $this->role === UserRole::Controller;
    }

    public function canVerify(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Verifier], true);
    }

    /**
     * Read scope: everything for an admin, one municipality for the rest
     * (nothing while unassigned). A controller reads its whole municipality
     * but writes only its own stations — see canWriteStation().
     */
    public function canReadMunicipality(int $municipalityId): bool
    {
        return $this->isAdmin() || ($this->municipality_id !== null && $this->municipality_id === $municipalityId);
    }

    public function canWriteStation(PollingStation $station): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->isController()) {
            return in_array($station->id, $this->assignedStationIds(), true);
        }

        return $this->role->writesWholeMunicipality() && $this->canReadMunicipality($station->municipality_id);
    }

    /** Whether the user may enter anything at all (a controller without stations cannot). */
    public function canWriteSomething(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->isController()) {
            return $this->assignedStationIds() !== [];
        }

        return $this->role->writesWholeMunicipality() && $this->municipality_id !== null;
    }

    /** @return array<int, int> */
    public function assignedStationIds(): array
    {
        return $this->stationIds ??= $this->pollingStations()->pluck('polling_stations.id')->map(fn ($id) => (int) $id)->all();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role instanceof UserRole;
    }
}
