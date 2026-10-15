<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Pages\Reports;
use App\Filament\Resources\PropertyResource;
use App\Filament\Widgets\PropertyKpis;
use App\Services\DocumentStorage;
use App\Services\GoogleDriveService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PropertyKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('report')->label('Auswertung')->icon('heroicon-o-chart-bar')->color('gray')
                ->url(fn () => Reports::getUrl(['property' => $this->record->id])),
            Actions\Action::make('drive')->label('Drive-Ordner')->icon('heroicon-o-folder-open')->color('gray')
                ->action(function () {
                    try {
                        $id = app(DocumentStorage::class)->propertyFolder($this->record);
                        $this->redirect('https://drive.google.com/drive/folders/'.$id);
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Drive nicht erreichbar')->body(GoogleDriveService::describeError($e))->send();
                    }
                })
                ->visible(fn () => app(GoogleDriveService::class)->isConnected()),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
