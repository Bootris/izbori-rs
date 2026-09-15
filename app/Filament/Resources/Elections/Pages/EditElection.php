<?php

declare(strict_types=1);

namespace App\Filament\Resources\Elections\Pages;

use App\Enums\SnapshotSource;
use App\Filament\Resources\Elections\ElectionResource;
use App\Models\Election;
use App\Services\Snapshots\SnapshotPublisher;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditElection extends EditRecord
{
    protected static string $resource = ElectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Objavi snapshot')
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color('success')
                ->schema([
                    Select::make('source')
                        ->label('Izvor')
                        ->options(SnapshotSource::class)
                        ->default(SnapshotSource::Results->value)
                        ->required()
                        ->native(false),
                ])
                ->requiresConfirmation()
                ->modalDescription('Generiše novu verziju fajlova i prebacuje config.json na nju. Prethodne verzije ostaju.')
                ->action(function (Election $record, array $data, SnapshotPublisher $publisher) {
                    try {
                        $snapshot = $publisher->publish($record, SnapshotSource::from($data['source']), auth()->user());
                        Notification::make()->success()
                            ->title("Objavljeno: {$snapshot->source->value} {$snapshot->version}")
                            ->body("{$snapshot->file_count} fajlova, {$snapshot->duration_ms} ms")
                            ->send();
                    } catch (Throwable $e) {
                        report($e);
                        Notification::make()->danger()->title('Objava nije uspela')->body($e->getMessage())->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Status changes alter which elections index.json lists as active.
        app(SnapshotPublisher::class)->refreshIndex();
    }
}
