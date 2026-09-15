<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols\Actions;

use App\Enums\ProtocolStatus;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/** Verify / annul actions shared by the table and the edit/view pages. */
final class ProtocolActions
{
    public static function verify(): Action
    {
        return Action::make('verify')
            ->label('Verifikuj')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Verifikuj zapisnik')
            ->modalDescription('Posle verifikacije zapisnik ulazi u zbir i objavljuje se pri sledećem snapshot-u.')
            ->visible(fn (Protocol $record) => $record->status === ProtocolStatus::Entered && (auth()->user()?->canVerify() ?? false))
            ->action(function (Protocol $record) {
                try {
                    app(ProtocolService::class)->verify($record, auth()->user());
                    Notification::make()->title('Zapisnik verifikovan')->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title('Verifikacija nije moguća')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function annul(): Action
    {
        return Action::make('annul')
            ->label('Poništi')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('warning')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')->label('Razlog poništavanja')->required()->rows(3),
            ])
            ->visible(fn (Protocol $record) => $record->status !== ProtocolStatus::Annulled && (auth()->user()?->canVerify() ?? false))
            ->action(function (Protocol $record, array $data) {
                app(ProtocolService::class)->annul($record, auth()->user(), (string) $data['reason']);
                Notification::make()->title('Zapisnik poništen')->warning()->send();
            });
    }
}
