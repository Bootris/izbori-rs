<?php

declare(strict_types=1);

namespace App\Filament\Resources\Incidents\Pages;

use App\Filament\Resources\Incidents\IncidentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewIncident extends ViewRecord
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            IncidentResource::reviewAction(),
            IncidentResource::closeAction(),
            IncidentResource::publishAction(),
        ];
    }
}
