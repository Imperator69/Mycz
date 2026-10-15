<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Enums\DocumentType;
use App\Filament\Resources\DocumentResource;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected static ?string $title = 'Dokument hochladen';

    public function form(Form $form): Form
    {
        return $form->schema(DocumentResource::uploadSchema(defaultType: request()->enum('type', DocumentType::class)));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $doc = DocumentResource::storeUpload($data);
        if (! $doc) {
            throw new Halt;
        }

        return $doc;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
