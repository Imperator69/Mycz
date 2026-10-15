<?php

namespace App\Filament\Resources;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Filament\Resources\DocumentResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Document;
use App\Models\Property;
use App\Services\DocumentStorage;
use App\Services\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Dokumente';

    protected static ?string $modelLabel = 'Dokument';

    protected static ?string $pluralModelLabel = 'Dokumente';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = Document::where('status', DocumentStatus::Inbox)->count();

        return $count > 0 ? (string) $count : null;
    }

    /** Felder zum Hochladen (auch in Relation Managern verwendet). */
    public static function uploadSchema(bool $withProperty = true, ?DocumentType $defaultType = null): array
    {
        return [
            Forms\Components\FileUpload::make('file')->label('Datei')->required()
                ->disk('local')->directory('upload-tmp')->visibility('private')
                ->storeFileNamesIn('original_name')
                ->maxSize(51200)
                ->acceptedFileTypes(['application/pdf', 'image/*', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/msword', 'application/vnd.ms-excel', 'text/csv', 'application/zip'])
                ->columnSpanFull(),
            Forms\Components\Grid::make(3)->schema([
                ...($withProperty ? [Fields::property(required: false)->helperText('Leer = allgemeines Dokument')] : []),
                Forms\Components\Select::make('doc_type')->label('Dokumenttyp')->options(DocumentType::class)->required()
                    ->default($defaultType ?? DocumentType::Invoice)->live()
                    ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('to_inbox', ($state instanceof DocumentType ? $state : DocumentType::tryFrom((string) $state)) === DocumentType::AnnualStatement)),
                Forms\Components\TextInput::make('year')->label('Jahr')->numeric()->default((int) date('Y'))->required(),
                Forms\Components\TextInput::make('title')->label('Titel')->helperText('Leer = Dateiname'),
                Forms\Components\DatePicker::make('document_date')->label('Dokumentdatum'),
                Forms\Components\Toggle::make('to_inbox')->label('Durch Claude auswerten lassen')->inline(false)
                    ->default(($defaultType ?? DocumentType::Invoice) === DocumentType::AnnualStatement)
                    ->helperText('Ablage in _Eingang; Claude liest die Abrechnung beim nächsten Monatslauf aus.'),
            ]),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(2)->columnSpanFull(),
        ];
    }

    /** Hochgeladene Datei an DocumentStorage uebergeben. */
    public static function storeUpload(array $data, array $extra = []): ?Document
    {
        $path = Storage::disk('local')->path($data['file']);
        $originalName = $data['original_name'] ?? basename($data['file']);

        try {
            $doc = app(DocumentStorage::class)->store($path, $originalName, array_filter([
                'property_id' => $data['property_id'] ?? null,
                'doc_type' => $data['doc_type'],
                'year' => (int) $data['year'],
                'title' => $data['title'] ?? null,
                'document_date' => $data['document_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => $v !== null && $v !== '') + $extra, (bool) ($data['to_inbox'] ?? false));

            Notification::make()->success()->title('Dokument gespeichert')
                ->body($doc->drive_file_id ? 'Im Google Drive abgelegt.' : 'Lokal gespeichert (Drive nicht verbunden).')->send();

            return $doc;
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Upload fehlgeschlagen')->body(GoogleDriveService::describeError($e))->persistent()->send();

            return null;
        } finally {
            Storage::disk('local')->delete($data['file']);
        }
    }

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\TextInput::make('title')->label('Titel')->required()->columnSpan(2),
            Forms\Components\Select::make('doc_type')->label('Dokumenttyp')->options(DocumentType::class)->required(),
            Fields::property(required: false),
            Forms\Components\TextInput::make('year')->label('Jahr')->numeric(),
            Forms\Components\DatePicker::make('document_date')->label('Dokumentdatum'),
            Forms\Components\Select::make('status')->label('Status')->options(DocumentStatus::class)->disabled(),
            Forms\Components\Placeholder::make('drive')->label('Datei')
                ->content(fn (?Document $record) => $record?->url() ? new HtmlString('<a class="text-primary-600 underline" target="_blank" href="'.e($record->url()).'">'.e($record->original_name).' öffnen</a>') : '–'),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Titel')->searchable()->limit(50)->weight('medium')
                    ->description(fn (Document $r) => $r->original_name),
                Tables\Columns\TextColumn::make('doc_type')->label('Typ')->badge()->sortable(),
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('year')->label('Jahr')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (DocumentStatus $state) => $state === DocumentStatus::Inbox ? 'warning' : 'success'),
                Tables\Columns\IconColumn::make('drive_file_id')->label('Drive')->boolean()->state(fn (Document $r) => (bool) $r->drive_file_id),
                Tables\Columns\TextColumn::make('size')->label('Größe')->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2, ',', '.').' MB' : null)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Hochgeladen')->date()->sortable()->toggleable(),
            ])
            ->filters([
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\SelectFilter::make('doc_type')->label('Typ')->options(DocumentType::class)->multiple(),
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(DocumentStatus::class),
                Tables\Filters\SelectFilter::make('year')->label('Jahr')
                    ->options(fn () => Document::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label('Öffnen')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->url(fn (Document $r) => $r->url(), true)->visible(fn (Document $r) => (bool) $r->url()),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('archive')->label('Ins Archiv verschieben')->icon('heroicon-o-archive-box')
                        ->visible(fn (Document $r) => $r->status === DocumentStatus::Inbox)
                        ->action(fn (Document $r) => static::safely(fn () => app(DocumentStorage::class)->archive($r), 'Archiviert')),
                    Tables\Actions\Action::make('resort')->label('Im Drive neu einsortieren')->icon('heroicon-o-arrows-pointing-in')
                        ->visible(fn (Document $r) => $r->drive_file_id && $r->status === DocumentStatus::Archived)
                        ->action(fn (Document $r) => static::safely(function () use ($r) {
                            $storage = app(DocumentStorage::class);
                            app(GoogleDriveService::class)->move($r->drive_file_id, $storage->targetFolder($r));
                        }, 'Verschoben')),
                    Tables\Actions\DeleteAction::make()->modalDescription('Löscht nur den Eintrag in der App. Die Datei im Google Drive bleibt erhalten.'),
                ]),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function safely(callable $fn, string $success): void
    {
        try {
            $fn();
            Notification::make()->success()->title($success)->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Fehler')->body(GoogleDriveService::describeError($e))->send();
        }
    }

    /** Aktion "Bestehende Drive-Datei verknuepfen". */
    public static function linkDriveAction(string $class, array $extra = [], bool $withProperty = true): Tables\Actions\Action|Action
    {
        /** @var Tables\Actions\Action|Action $class */
        return $class::make('linkDrive')->label('Drive-Datei verknüpfen')->icon('heroicon-o-link')->color('gray')
            ->visible(fn () => app(GoogleDriveService::class)->isConnected())
            ->form([
                Forms\Components\Select::make('file_id')->label('Datei im Google Drive suchen')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => collect(app(GoogleDriveService::class)->search($search))->pluck('name', 'id')->all()),
                Forms\Components\Grid::make(3)->schema([
                    ...($withProperty ? [Forms\Components\Select::make('property_id')->label('Objekt')->options(fn () => Property::orderBy('name')->pluck('name', 'id'))->searchable()] : []),
                    Forms\Components\Select::make('doc_type')->label('Dokumenttyp')->options(DocumentType::class)->required()->default(DocumentType::Other),
                    Forms\Components\TextInput::make('year')->label('Jahr')->numeric()->default((int) date('Y')),
                ]),
            ])
            ->action(fn (array $data) => static::safely(fn () => app(DocumentStorage::class)->linkExisting($data['file_id'], array_filter([
                'property_id' => $data['property_id'] ?? null,
                'doc_type' => $data['doc_type'],
                'year' => $data['year'] ?? null,
            ]) + $extra), 'Verknüpft'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'original_name'];
    }
}
