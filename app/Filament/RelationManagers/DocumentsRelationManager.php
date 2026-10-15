<?php

namespace App\Filament\RelationManagers;

use App\Enums\DocumentType;
use App\Filament\Resources\DocumentResource;
use App\Models\Property;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Dokumente unter einem Objekt bzw. einem Vertrag/Polizze/Kredit.
 * Upload landet automatisch im passenden Drive-Ordner des Objekts.
 */
abstract class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Dokumente';

    protected static ?string $modelLabel = 'Dokument';

    protected static ?string $icon = 'heroicon-o-folder';

    protected static ?DocumentType $defaultType = null;

    public function form(Form $form): Form
    {
        return DocumentResource::form($form);
    }

    public function table(Table $table): Table
    {
        return DocumentResource::table($table)
            ->headerActions([
                Tables\Actions\Action::make('upload')->label('Hochladen')->icon('heroicon-o-arrow-up-tray')
                    ->form(DocumentResource::uploadSchema(withProperty: false, defaultType: static::$defaultType))
                    ->action(fn (array $data) => DocumentResource::storeUpload($data, $this->ownerAttributes())),
                DocumentResource::linkDriveAction(Tables\Actions\Action::class, $this->ownerAttributes(), withProperty: false),
            ]);
    }

    /** @return array<string, mixed> */
    protected function ownerAttributes(): array
    {
        $owner = $this->getOwnerRecord();
        if ($owner instanceof Property) {
            return ['property_id' => $owner->id];
        }

        $attributes = ['property_id' => $owner->property_id ?? null];
        if ($owner->{static::$relationship}() instanceof MorphMany) {
            $attributes['documentable_type'] = $owner->getMorphClass();
            $attributes['documentable_id'] = $owner->getKey();
        }

        return $attributes;
    }
}
