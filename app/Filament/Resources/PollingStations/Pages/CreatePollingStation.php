<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations\Pages;

use App\Filament\Resources\PollingStations\PollingStationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePollingStation extends CreateRecord
{
    protected static string $resource = PollingStationResource::class;
}
