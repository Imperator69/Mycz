<?php

namespace App\Filament\Resources\PropertyResource\Pages;

use App\Filament\Resources\PropertyResource;
use App\Services\DocumentStorage;
use App\Services\GoogleDriveService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected function afterFill(): void
    {
        if ($parent = request()->integer('parent')) {
            $this->data['parent_id'] = $parent;
        }
    }

    protected function afterCreate(): void
    {
        $drive = app(GoogleDriveService::class);
        if (! $drive->isConnected()) {
            return;
        }

        try {
            app(DocumentStorage::class)->propertyFolder($this->record);
        } catch (\Throwable $e) {
            Notification::make()->warning()->title('Drive-Ordner nicht angelegt')->body(GoogleDriveService::describeError($e))->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
