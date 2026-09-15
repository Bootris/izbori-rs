<?php

declare(strict_types=1);

namespace App\Filament\Resources\TurnoutSnapshots\Pages;

use App\Filament\Resources\TurnoutSnapshots\TurnoutSnapshotResource;
use App\Support\Access;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTurnoutSnapshots extends ManageRecords
{
    protected static string $resource = TurnoutSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['entered_by' => Access::user()?->id]),
        ];
    }
}
