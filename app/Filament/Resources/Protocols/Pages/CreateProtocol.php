<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Pages;

use App\Enums\ProtocolStatus;
use App\Filament\Resources\Protocols\ProtocolResource;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolService;
use App\Support\Access;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProtocol extends CreateRecord
{
    protected static string $resource = ProtocolResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$attributes, $votes, $scans] = ProtocolResource::splitFormData($data);

        $protocol = new Protocol([
            'election_id' => $attributes['election_id'],
            'polling_station_id' => $attributes['polling_station_id'],
            'round' => $attributes['round'] ?? 1,
        ]);

        $protocol = app(ProtocolService::class)->save($protocol, $attributes, $votes, Access::user());
        ProtocolResource::syncScans($protocol, $scans);

        if ($protocol->status === ProtocolStatus::Flagged) {
            Notification::make()->warning()
                ->title('Zapisnik sačuvan sa odstupanjem')
                ->body(implode(' ', $protocol->validation_errors ?? []))
                ->persistent()
                ->send();
        }

        return $protocol;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
