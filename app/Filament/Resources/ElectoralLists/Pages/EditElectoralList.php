<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectoralLists\Pages;

use App\Filament\Resources\ElectoralLists\ElectoralListResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditElectoralList extends EditRecord
{
    protected static string $resource = ElectoralListResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
