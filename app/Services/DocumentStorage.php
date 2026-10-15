<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Property;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Legt Dokumente im Google Drive ab (Ordnerstruktur je Objekt/Jahr/Kategorie).
 * Ohne Drive-Verbindung wird lokal gespeichert und spaeter synchronisiert.
 */
class DocumentStorage
{
    /** Ordner unter Objekt/<Jahr>/... bzw. Objekt/Dauerhaft */
    public const PERMANENT = 'Dauerhaft';

    public function __construct(private readonly GoogleDriveService $drive) {}

    public static function folderFor(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::AnnualStatement => ['year', 'Betriebskosten'],
            DocumentType::Invoice => ['year', 'Rechnungen'],
            DocumentType::Tax => ['year', 'Steuer'],
            DocumentType::Photo => ['year', 'Fotos'],
            DocumentType::Correspondence => ['year', 'Korrespondenz'],
            DocumentType::Other => ['year', 'Sonstiges'],
            DocumentType::InsurancePolicy => [self::PERMANENT, 'Versicherung'],
            DocumentType::LeaseContract => [self::PERMANENT, 'Mietvertraege'],
            DocumentType::PurchaseContract, DocumentType::LandRegister => [self::PERMANENT, 'Kauf und Grundbuch'],
            DocumentType::LoanContract => [self::PERMANENT, 'Kredit'],
            DocumentType::Protocol => [self::PERMANENT, 'Protokolle'],
            DocumentType::Plan => [self::PERMANENT, 'Plaene'],
        };
    }

    /**
     * Neue Datei speichern.
     *
     * @param  array<string, mixed>  $attributes  Document-Attribute (property_id, doc_type, year, title ...)
     * @param  bool  $toInbox  in _Eingang ablegen (zur Auswertung durch Claude)
     */
    public function store(string $path, string $originalName, array $attributes, bool $toInbox = false): Document
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Datei konnte nicht gelesen werden.');
        }

        $hash = hash('sha256', $contents);
        $duplicate = Document::where('sha256', $hash)->first();
        if ($duplicate) {
            throw new RuntimeException("Diese Datei ist bereits vorhanden: \"{$duplicate->title}\" (#{$duplicate->id}).");
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $document = Document::create(array_merge([
            'title' => pathinfo($originalName, PATHINFO_FILENAME),
            'doc_type' => DocumentType::Other,
            'year' => (int) date('Y'),
        ], $attributes, [
            'original_name' => $originalName,
            'mime' => $mime,
            'size' => strlen($contents),
            'sha256' => $hash,
            'status' => $toInbox ? DocumentStatus::Inbox : DocumentStatus::Archived,
        ]));

        if ($this->drive->isConnected()) {
            $this->uploadToDrive($document, $contents);
        } else {
            $local = 'documents/'.$document->id.'-'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.pathinfo($originalName, PATHINFO_EXTENSION);
            Storage::disk('local')->put($local, $contents);
            $document->update(['local_path' => $local]);
        }

        return $document;
    }

    /** Lokal gespeicherte Dokumente nachtraeglich ins Drive hochladen. */
    public function syncLocalToDrive(): int
    {
        $count = 0;
        Document::whereNull('drive_file_id')->whereNotNull('local_path')->each(function (Document $doc) use (&$count) {
            if (! Storage::disk('local')->exists($doc->local_path)) {
                return;
            }
            $this->uploadToDrive($doc, Storage::disk('local')->get($doc->local_path));
            Storage::disk('local')->delete($doc->local_path);
            $doc->update(['local_path' => null]);
            $count++;
        });

        return $count;
    }

    /** Eingangs-Dokument in das Archiv des Objekts verschieben. */
    public function archive(Document $document): void
    {
        if ($document->status === DocumentStatus::Archived) {
            return;
        }
        if ($document->drive_file_id && $this->drive->isConnected()) {
            $this->drive->move($document->drive_file_id, $this->targetFolder($document));
        }
        $document->update(['status' => DocumentStatus::Archived]);
    }

    public function targetFolder(Document $document): string
    {
        $base = $document->property ? $this->propertyFolder($document->property) : $this->drive->ensurePath($this->drive->rootId(), ['_Allgemein']);
        [$first, $second] = self::folderFor($document->doc_type);
        if ($first === 'year') {
            $first = (string) ($document->year ?: date('Y'));
        }

        return $this->drive->ensurePath($base, [$first, $second]);
    }

    public function propertyFolder(Property $property): string
    {
        if ($property->drive_folder_id) {
            return $property->drive_folder_id;
        }

        $parent = $property->parent ? $this->propertyFolder($property->parent) : $this->drive->rootId();
        $id = $this->drive->ensurePath($parent, [$property->driveFolderName()]);
        $this->drive->ensurePath($id, [self::PERMANENT]);
        $property->forceFill(['drive_folder_id' => $id])->saveQuietly();

        return $id;
    }

    /** Bestehende Drive-Datei verknuepfen (ohne Upload). */
    public function linkExisting(string $fileId, array $attributes): Document
    {
        $existing = Document::where('drive_file_id', $fileId)->first();
        if ($existing) {
            throw new RuntimeException("Diese Drive-Datei ist bereits verknüpft: \"{$existing->title}\".");
        }
        $file = $this->drive->getFile($fileId);

        return Document::create(array_merge([
            'title' => pathinfo($file['name'], PATHINFO_FILENAME),
            'year' => (int) date('Y'),
        ], $attributes, [
            'original_name' => $file['name'],
            'mime' => $file['mimeType'] ?? null,
            'size' => isset($file['size']) ? (int) $file['size'] : null,
            'drive_file_id' => $file['id'],
            'drive_web_link' => $file['webViewLink'] ?? null,
            'status' => DocumentStatus::Archived,
        ]));
    }

    private function uploadToDrive(Document $document, string $contents): void
    {
        $folder = $document->status === DocumentStatus::Inbox ? $this->drive->inboxId() : $this->targetFolder($document);
        $name = $this->driveFileName($document);
        $description = sprintf(
            'MYCZ Dokument #%d | Objekt: %s | Typ: %s | Jahr: %s',
            $document->id,
            $document->property ? $document->property->name.' #'.$document->property_id : '-',
            $document->doc_type->getLabel(),
            $document->year ?? '-',
        );

        $file = $this->drive->upload($contents, $name, $document->mime ?? 'application/octet-stream', $folder, $description);
        $document->update([
            'drive_file_id' => $file['id'],
            'drive_web_link' => $file['webViewLink'] ?? null,
        ]);
    }

    private function driveFileName(Document $document): string
    {
        $ext = pathinfo((string) $document->original_name, PATHINFO_EXTENSION);
        $date = ($document->document_date ?? $document->created_at)->format('Y-m-d');
        $name = $date.' '.Str::limit($document->title, 80, '');

        return $name.($ext ? '.'.$ext : '');
    }
}
