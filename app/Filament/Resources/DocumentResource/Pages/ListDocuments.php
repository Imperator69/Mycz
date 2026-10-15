<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Services\DocumentStorage;
use App\Services\GoogleDriveService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Hochladen')->icon('heroicon-o-arrow-up-tray'),
            DocumentResource::linkDriveAction(Actions\Action::class),
            Actions\Action::make('sync')->label('Lokale Dateien nach Drive')->icon('heroicon-o-cloud-arrow-up')->color('gray')
                ->visible(fn () => app(GoogleDriveService::class)->isConnected())
                ->action(fn () => DocumentResource::safely(fn () => app(DocumentStorage::class)->syncLocalToDrive(), 'Synchronisiert')),
        ];
    }
}
