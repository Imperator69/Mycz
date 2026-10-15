<?php

namespace App\Filament\Pages;

use App\Models\Property;
use App\Models\Setting;
use App\Services\BackupService;
use App\Services\DocumentStorage;
use App\Services\GoogleDriveService;
use App\Services\Housekeeping;
use App\Services\StatementImporter;
use App\Services\StatusExporter;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $title = 'Einstellungen & Google Drive';

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.settings';

    protected function getViewData(): array
    {
        $drive = app(GoogleDriveService::class);
        $rootId = Setting::get('drive.root_id');

        return [
            'configured' => $drive->isConfigured(),
            'connected' => $drive->isConnected(),
            'account' => $drive->connectedAccount(),
            'rootUrl' => $rootId ? $drive->folderUrl($rootId) : null,
            'redirectUri' => $drive->redirectUri(),
            'lastHousekeeping' => Setting::get('housekeeping.last_run'),
            'lastStatusExport' => Setting::get('drive.status_exported_at'),
            'lastBackup' => Setting::get('backup.last_run'),
            'appUrl' => url('/admin'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $drive = app(GoogleDriveService::class);

        return [
            Action::make('connect')->label($drive->isConnected() ? 'Neu verbinden' : 'Google Drive verbinden')
                ->icon('heroicon-o-link')->url(route('google.connect'))
                ->visible($drive->isConfigured())
                ->color($drive->isConnected() ? 'gray' : 'primary'),
            Action::make('run')->label('Jetzt synchronisieren')->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $result = app(Housekeeping::class)->run();
                    $body = $result['charges_created'].' neue Soll-Mieten';
                    if (isset($result['status_exported'])) {
                        $body .= $result['status_exported'] ? ', status.json aktualisiert' : ', status.json Fehler: '.$result['status_error'];
                    }
                    Notification::make()->success()->title('Synchronisiert')->body($body)->send();
                }),
            Action::make('more')->label('Drive-Aktionen')->icon('heroicon-o-ellipsis-vertical')->color('gray')
                ->visible($drive->isConnected())
                ->form([
                    Forms\Components\Radio::make('task')->label('Aktion')->required()->options([
                        'bootstrap' => 'Ordnerstruktur anlegen (Immobilien, _Eingang, _System)',
                        'folders' => 'Ordner für alle Objekte anlegen',
                        'status' => 'status.json für Claude exportieren',
                        'import' => 'Claude-Auswertungen importieren',
                        'sync' => 'Lokal gespeicherte Dokumente hochladen',
                        'backup' => 'Daten-Backup jetzt ins Drive schreiben',
                        'cache' => 'Ordner-Zwischenspeicher leeren',
                    ]),
                    Forms\Components\TextInput::make('root_id')->label('Bestehenden Root-Ordner verwenden (ID, optional)')
                        ->visible(fn (Forms\Get $get) => $get('task') === 'bootstrap'),
                ])
                ->action(function (array $data) use ($drive) {
                    try {
                        $message = match ($data['task']) {
                            'bootstrap' => (function () use ($drive, $data) {
                                if (! empty($data['root_id'])) {
                                    $drive->getFile($data['root_id']);
                                    Setting::put('drive.root_id', $data['root_id']);
                                    $drive->forgetFolderCache();
                                }
                                $drive->bootstrap();

                                return 'Struktur angelegt';
                            })(),
                            'folders' => (function () {
                                $storage = app(DocumentStorage::class);
                                $n = 0;
                                Property::orderBy('parent_id')->each(function ($p) use ($storage, &$n) {
                                    $storage->propertyFolder($p);
                                    $n++;
                                });

                                return "$n Objektordner geprüft";
                            })(),
                            'status' => (app(StatusExporter::class)->export() ? 'status.json aktualisiert' : ''),
                            'import' => (function () {
                                $r = app(StatementImporter::class)->importFromDrive();

                                return $r['imported'].' importiert'.($r['errors'] ? ', Fehler: '.implode('; ', $r['errors']) : '');
                            })(),
                            'sync' => app(DocumentStorage::class)->syncLocalToDrive().' Dokument(e) hochgeladen',
                            'backup' => (app(BackupService::class)->export() ? 'Backup in _System/backups gespeichert' : ''),
                            'cache' => (function () use ($drive) {
                                $drive->forgetFolderCache();

                                return 'Zwischenspeicher geleert';
                            })(),
                        };
                        Notification::make()->success()->title($message)->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Fehler')->body(GoogleDriveService::describeError($e))->send();
                    }
                }),
            Action::make('disconnect')->label('Trennen')->icon('heroicon-o-x-mark')->color('danger')
                ->visible($drive->isConnected())
                ->requiresConfirmation()
                ->modalDescription('Die App verliert den Zugriff. Dateien im Drive bleiben erhalten.')
                ->action(fn () => $drive->disconnect()),
        ];
    }
}
