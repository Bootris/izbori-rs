<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectoralLists\Pages;

use App\Filament\Resources\ElectoralLists\ElectoralListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListElectoralLists extends ListRecords
{
    protected static string $resource = ElectoralListResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
