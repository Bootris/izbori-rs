<?php

declare(strict_types=1);

namespace App\Filament\Resources\Submitters\Pages;

use App\Filament\Resources\Submitters\SubmitterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSubmitters extends ManageRecords
{
    protected static string $resource = SubmitterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
