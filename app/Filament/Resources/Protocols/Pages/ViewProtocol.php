<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Pages;

use App\Filament\Resources\Protocols\ProtocolResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewProtocol extends ViewRecord
{
    protected static string $resource = ProtocolResource::class;

    /** The infolist walks items → list and revisions → user; load them once instead of per row. */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['items.list', 'scans', 'revisions.user']);
    }

    protected function getHeaderActions(): array
    {
        return [
            ProtocolResource::verifyAction(),
            ProtocolResource::returnAction(),
            ProtocolResource::annulAction(),
            ProtocolResource::revalidateAction(),
            EditAction::make(),
        ];
    }
}
