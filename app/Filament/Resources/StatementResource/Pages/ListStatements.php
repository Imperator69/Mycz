<?php

namespace App\Filament\Resources\StatementResource\Pages;

use App\Enums\DocumentType;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\StatementResource;
use App\Services\GoogleDriveService;
use App\Services\StatementImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStatements extends ListRecords
{
    protected static string $resource = StatementResource::class;

    protected ?string $subheading = 'Abrechnung hochladen, Claude wertet sie monatlich aus, hier prüfen und buchen.';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')->label('Abrechnung hochladen')->icon('heroicon-o-arrow-up-tray')
                ->url(DocumentResource::getUrl('create', ['type' => DocumentType::AnnualStatement->value])),
            Actions\Action::make('import')->label('Auswertungen aus Drive holen')->icon('heroicon-o-cloud-arrow-down')
                ->visible(fn () => app(GoogleDriveService::class)->isConnected())
                ->action(function () {
                    try {
                        $result = app(StatementImporter::class)->importFromDrive();
                        $n = Notification::make()->title($result['imported'].' Auswertung(en) importiert')
                            ->body($result['errors'] ? implode("\n", $result['errors']) : null);
                        ($result['errors'] ? $n->warning()->persistent() : $n->success())->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Import fehlgeschlagen')->body(GoogleDriveService::describeError($e))->send();
                    }
                }),
            Actions\Action::make('json')->label('JSON manuell importieren')->icon('heroicon-o-code-bracket')->color('gray')
                ->form([
                    Forms\Components\Textarea::make('json')->label('Auswertung (mycz.statement.v1)')->rows(14)->required()
                        ->helperText('z.B. aus einem Claude-Chat kopiert'),
                ])
                ->action(function (array $data) {
                    try {
                        $statement = app(StatementImporter::class)->import(json_decode($data['json'], true, 512, JSON_THROW_ON_ERROR));
                        $this->redirect(StatementResource::getUrl('edit', ['record' => $statement]));
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Ungültige Auswertung')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
