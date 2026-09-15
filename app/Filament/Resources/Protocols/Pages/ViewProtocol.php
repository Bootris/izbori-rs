<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Pages;

use App\Filament\Resources\Protocols\ProtocolResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProtocol extends ViewRecord
{
    protected static string $resource = ProtocolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProtocolResource::verifyAction(),
            ProtocolResource::annulAction(),
            ProtocolResource::revalidateAction(),
            EditAction::make(),
        ];
    }
}
