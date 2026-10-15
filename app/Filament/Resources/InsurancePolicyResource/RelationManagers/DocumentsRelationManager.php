<?php

namespace App\Filament\Resources\InsurancePolicyResource\RelationManagers;

use App\Enums\DocumentType;
use App\Filament\RelationManagers\DocumentsRelationManager as BaseDocumentsRelationManager;

class DocumentsRelationManager extends BaseDocumentsRelationManager
{
    protected static ?DocumentType $defaultType = DocumentType::InsurancePolicy;
}
