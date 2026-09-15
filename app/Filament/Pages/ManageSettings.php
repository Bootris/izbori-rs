<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Access;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Podešavanja sajta';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Access::isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(Setting::allCached());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitet')
                    ->columns(2)
                    ->components([
                        TextInput::make('site_name')->label('Naziv sajta')->required()->helperText('Naslov u adminu i na javnom sajtu.'),
                        TextInput::make('publisher')->label('Izdavač podataka')->helperText('Npr. Republička izborna komisija.'),
                        TextInput::make('contact_email')->label('Kontakt email')->email(),
                        TextInput::make('methodology_url')->label('Link ka metodologiji / otvorenim podacima')->url(),
                        Textarea::make('public_notice')
                            ->label('Obaveštenje na javnom sajtu')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Prikazuje se kao traka ispod zaglavlja (npr. „Preliminarni rezultati, obrada u toku"). Prazno = bez trake. Primenjuje se pri sledećoj objavi.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        // Site name / notice live in /data/index.json — push them without a full publish.
        app(SnapshotPublisher::class)->refreshIndex();

        Notification::make()->title('Podešavanja sačuvana')->success()->send();
    }
}
