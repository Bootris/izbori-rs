<?php

declare(strict_types=1);

namespace App\Filament\Resources\Incidents\Pages;

use App\Filament\Resources\Incidents\IncidentResource;
use App\Services\Incidents\IncidentService;
use App\Support\Access;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateIncident extends CreateRecord
{
    protected static string $resource = IncidentResource::class;

    protected static ?string $title = 'Prijava problema na biračkom mestu';

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Pošalji prijavu')->color('danger');
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(IncidentService::class)->report($data, Access::user());
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()
            ->title('Prijava poslata')
            ->body('Vreme prijave je zabeleženo, svi korisnici platforme su obavešteni.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
