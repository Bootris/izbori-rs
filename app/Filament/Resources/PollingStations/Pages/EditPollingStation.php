<?php

declare(strict_types=1);

namespace App\Filament\Resources\PollingStations\Pages;

use App\Filament\Resources\PollingStations\PollingStationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPollingStation extends EditRecord
{
    protected static string $resource = PollingStationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
