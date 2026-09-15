<?php

declare(strict_types=1);

namespace App\Filament\Resources\Snapshots\Pages;

use App\Enums\SnapshotSource;
use App\Filament\Resources\Snapshots\SnapshotResource;
use App\Models\Election;
use App\Services\Snapshots\SnapshotPublisher;
use App\Support\Access;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListSnapshots extends ListRecords
{
    protected static string $resource = SnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Nova objava')
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color('success')
                ->schema([
                    Select::make('election_id')->label('Izbori')
                        ->options(fn () => Election::query()->orderByDesc('election_date')->pluck('name', 'id'))
                        ->required()->native(false),
                    Select::make('source')->label('Izvor')->options(SnapshotSource::class)->default(SnapshotSource::Results->value)->required()->native(false),
                ])
                ->action(function (array $data, SnapshotPublisher $publisher) {
                    try {
                        $snapshot = $publisher->publish(Election::findOrFail($data['election_id']), SnapshotSource::from($data['source']), Access::user());
                        Notification::make()->success()
                            ->title("Objavljeno: {$snapshot->source->value} {$snapshot->version}")
                            ->body("{$snapshot->file_count} fajlova, {$snapshot->duration_ms} ms")
                            ->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()->title('Objava nije uspela')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
