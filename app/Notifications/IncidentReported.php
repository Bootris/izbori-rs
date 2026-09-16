<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\IncidentSeverity;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Models\Incident;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The alarm: lands in every user's panel bell the moment a station reports a
 * problem. Sent synchronously on purpose; an alarm that waits for a queue
 * worker is not an alarm.
 */
class IncidentReported extends Notification
{
    public function __construct(public readonly Incident $incident) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $i = $this->incident;
        $station = $i->pollingStation;
        $when = $i->reported_at->format('H:i:s');

        return FilamentNotification::make()
            ->title("Prijava sa BM {$station->number}, {$i->municipality->name}: {$i->severity->shortLabel()}")
            ->body("{$when}, {$i->category->getLabel()}. ".Str::limit($i->description, 180))
            ->icon($this->icon($i->severity))
            ->iconColor($i->severity->getColor())
            ->actions([
                Action::make('view')->label('Pregledaj prijavu')->button()->url(IncidentResource::getUrl('view', ['record' => $i])),
            ])
            ->getDatabaseMessage();
    }

    private function icon(IncidentSeverity $severity): string
    {
        return match ($severity) {
            IncidentSeverity::Critical, IncidentSeverity::High => 'heroicon-o-bell-alert',
            default => 'heroicon-o-flag',
        };
    }
}
