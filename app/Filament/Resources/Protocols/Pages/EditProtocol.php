<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Pages;

use App\Enums\ProtocolStatus;
use App\Filament\Resources\Protocols\ProtocolResource;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolService;
use App\Support\Access;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProtocol extends EditRecord
{
    protected static string $resource = ProtocolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProtocolResource::verifyAction(),
            ProtocolResource::annulAction(),
            ProtocolResource::revalidateAction(),
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Protocol $record */
        $record = $this->getRecord();
        $data['votes'] = $record->items()->pluck('votes', 'electoral_list_id')->all();
        $data['scans'] = $record->scans()->pluck('path')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$attributes, $votes, $scans] = ProtocolResource::splitFormData($data);
        unset($attributes['election_id'], $attributes['polling_station_id'], $attributes['round']);

        /** @var Protocol $record */
        $record = app(ProtocolService::class)->save($record, $attributes, $votes, Access::user());
        ProtocolResource::syncScans($record, $scans);

        if ($record->status === ProtocolStatus::Flagged) {
            Notification::make()->warning()
                ->title('Zapisnik sačuvan sa odstupanjem — ne ulazi u zbir')
                ->body(implode(' ', $record->validation_errors ?? []))
                ->persistent()
                ->send();
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
