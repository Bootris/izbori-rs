<?php

declare(strict_types=1);

namespace App\Filament\Resources\Protocols;

use App\Enums\ProtocolStatus;
use App\Filament\Resources\Protocols\Pages\CreateProtocol;
use App\Filament\Resources\Protocols\Pages\EditProtocol;
use App\Filament\Resources\Protocols\Pages\ListProtocols;
use App\Filament\Resources\Protocols\Pages\ViewProtocol;
use App\Filament\Resources\Protocols\Schemas\ProtocolForm;
use App\Filament\Resources\Protocols\Schemas\ProtocolInfolist;
use App\Filament\Resources\Protocols\Tables\ProtocolsTable;
use App\Models\Protocol;
use App\Models\ProtocolScan;
use App\Services\Protocols\ProtocolService;
use App\Support\Access;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ProtocolResource extends Resource
{
    protected static ?string $model = Protocol::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Zapisnici';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'zapisnik';

    protected static ?string $pluralModelLabel = 'Zapisnici biračkih odbora';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->flagged()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return Access::scopeThroughStation(parent::getEloquentQuery())
            ->with(['pollingStation.municipality', 'election', 'enteredBy', 'verifiedBy']);
    }

    public static function form(Schema $schema): Schema
    {
        return ProtocolForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProtocolInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProtocolsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProtocols::route('/'),
            'create' => CreateProtocol::route('/create'),
            'view' => ViewProtocol::route('/{record}'),
            'edit' => EditProtocol::route('/{record}/edit'),
        ];
    }

    // ---- shared actions (table rows + view/edit headers); visibility follows ProtocolPolicy

    public static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verifikuj')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Protocol $record): bool => Access::user()?->can('verify', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription('Verifikovan zapisnik ulazi u zbir i objavljuje se. Posle toga se menja samo preko „Vrati na ispravku" uz razlog.')
            ->action(function (Protocol $record, ProtocolService $service) {
                try {
                    $service->verify($record, Access::user());
                    Notification::make()->success()->title('Zapisnik verifikovan')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('Verifikacija nije moguća')->body($e->getMessage())->send();
                }
            });
    }

    /** The only door to editing a verified protocol: it leaves the aggregates again, with the reason on record. */
    public static function returnAction(): Action
    {
        return Action::make('return')
            ->label('Vrati na ispravku')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Protocol $record): bool => Access::user()?->can('unverify', $record) ?? false)
            ->schema([Textarea::make('reason')->label('Razlog vraćanja na ispravku')->required()->rows(3)])
            ->requiresConfirmation()
            ->modalDescription('Zapisnik izlazi iz zbira i vraća se u status „unet" dok se ne ispravi i ponovo verifikuje. Razlog ostaje u istoriji izmena.')
            ->action(function (Protocol $record, array $data, ProtocolService $service) {
                try {
                    $service->returnForCorrection($record, Access::user(), (string) $data['reason']);
                    Notification::make()->warning()->title('Zapisnik vraćen na ispravku')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('Vraćanje nije moguće')->body($e->getMessage())->send();
                }
            });
    }

    public static function annulAction(): Action
    {
        return Action::make('annul')
            ->label('Poništi')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('warning')
            ->visible(fn (Protocol $record): bool => Access::user()?->can('annul', $record) ?? false)
            ->schema([Textarea::make('reason')->label('Razlog poništenja')->required()->rows(3)])
            ->requiresConfirmation()
            ->action(function (Protocol $record, array $data, ProtocolService $service) {
                $service->annul($record, Access::user(), (string) $data['reason']);
                Notification::make()->warning()->title('Zapisnik poništen')->send();
            });
    }

    public static function revalidateAction(): Action
    {
        return Action::make('revalidate')
            ->label('Ponovo proveri')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Protocol $record): bool => Access::user()?->can('revalidate', $record) ?? false)
            ->action(function (Protocol $record, ProtocolService $service) {
                $record->status = ProtocolStatus::Entered;
                $service->revalidate($record, Access::user());
                Notification::make()->success()->title('Kontrolne sume ponovo izračunate')->send();
            });
    }

    /**
     * Splits Filament form data into protocol columns, votes per list and scan paths.
     *
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<int, int>, 2: array<int, string>}
     */
    public static function splitFormData(array $data): array
    {
        $votes = array_map('intval', (array) ($data['votes'] ?? []));
        $scans = array_values(array_filter((array) ($data['scans'] ?? [])));
        unset($data['votes'], $data['scans']);

        return [$data, $votes, $scans];
    }

    /** @param array<int, string> $paths */
    public static function syncScans(Protocol $protocol, array $paths): void
    {
        $existing = $protocol->scans()->pluck('path')->all();
        foreach (array_diff($paths, $existing) as $path) {
            ProtocolScan::create(['protocol_id' => $protocol->id, 'path' => $path, 'original_name' => basename($path), 'uploaded_by' => Access::user()?->id]);
        }
        $protocol->scans()->whereNotIn('path', $paths)->delete();
    }
}
