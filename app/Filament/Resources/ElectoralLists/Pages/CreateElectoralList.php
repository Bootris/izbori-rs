<?php

declare(strict_types=1);

namespace App\Filament\Resources\ElectoralLists\Pages;

use App\Filament\Resources\ElectoralLists\ElectoralListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateElectoralList extends CreateRecord
{
    protected static string $resource = ElectoralListResource::class;
}
