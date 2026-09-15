<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations\Pages;

use App\Filament\Resources\PollingStations\PollingStationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePollingStations extends ManageRecords
{
    protected static string $resource = PollingStationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
