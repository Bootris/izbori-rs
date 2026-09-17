<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnoutSnapshots\Pages;

use App\Filament\Resources\TurnoutSnapshots\TurnoutSnapshotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTurnoutSnapshots extends ManageRecords
{
    protected static string $resource = TurnoutSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Unesi presek')
                ->using(fn (array $data) => TurnoutSnapshotResource::record($data)),
        ];
    }
}
