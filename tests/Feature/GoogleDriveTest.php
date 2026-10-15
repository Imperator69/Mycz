<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Setting;
use App\Services\BackupService;
use App\Services\DocumentStorage;
use App\Services\GoogleDriveService;
use App\Services\StatementImporter;
use App\Services\StatusExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleDriveTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Request> */
    private array $requests = [];

    private int $nextId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'cid', 'services.google.client_secret' => 'secret']);
        Setting::put('google.refresh_token', 'refresh');
        Setting::put('google.access_token', ['token' => 'tok', 'expires_at' => time() + 3600]);
        Setting::put('drive.root_id', 'ROOT');
    }

    /** Minimaler Drive-Fake: Ordner-Suche leer, Erstellen liefert fortlaufende IDs. */
    private function fakeDrive(array $files = [], array $downloads = []): void
    {
        Http::fake(function (Request $request) use ($files, $downloads) {
            $this->requests[] = $request;
            $url = $request->url();

            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'new', 'expires_in' => 3600]);
            }
            if ($request->method() === 'GET' && str_contains($url, '/drive/v3/files?')) {
                parse_str(parse_url($url, PHP_URL_QUERY), $query);
                foreach ($files as $needle => $result) {
                    if (str_contains($query['q'], $needle)) {
                        return Http::response(['files' => $result]);
                    }
                }

                return Http::response(['files' => []]);
            }
            if ($request->method() === 'GET' && str_contains($url, 'alt=media')) {
                preg_match('#/files/([^?]+)#', $url, $m);

                return Http::response($downloads[$m[1]] ?? '');
            }
            if ($request->method() === 'GET' && preg_match('#/drive/v3/files/([^?]+)#', $url, $m)) {
                return Http::response(['id' => $m[1], 'name' => 'Datei.pdf', 'parents' => ['OLD'], 'mimeType' => 'application/pdf', 'webViewLink' => 'https://drive/'.$m[1]]);
            }
            if ($request->method() === 'POST') {
                $id = 'ID'.$this->nextId++;

                return Http::response(['id' => $id, 'webViewLink' => 'https://drive/'.$id]);
            }

            return Http::response([]);
        });
    }

    public function test_upload_creates_folder_path_and_stores_drive_link(): void
    {
        $this->fakeDrive();
        $property = $this->makeProperty(['name' => 'Wohnung / Top 3']);
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, '%PDF-1.4 test');

        $doc = app(DocumentStorage::class)->store($path, 'Rechnung Installateur.pdf', [
            'property_id' => $property->id, 'doc_type' => DocumentType::Invoice, 'year' => 2026,
        ]);

        $this->assertNotNull($doc->drive_file_id);
        $this->assertStringStartsWith('https://drive/', $doc->drive_web_link);
        $this->assertNull($doc->local_path);
        $this->assertNotNull($property->refresh()->drive_folder_id);

        $folderNames = collect($this->requests)
            ->filter(fn (Request $r) => $r->method() === 'POST' && ($r->data()['mimeType'] ?? null) === GoogleDriveService::FOLDER_MIME)
            ->map(fn (Request $r) => $r->data()['name'])->values()->all();
        $this->assertSame(['Wohnung - Top 3 #'.$property->id, 'Dauerhaft', '2026', 'Rechnungen'], $folderNames);

        $upload = collect($this->requests)->first(fn (Request $r) => str_contains($r->url(), 'uploadType=multipart'));
        $this->assertStringContainsString('"name":"'.now()->format('Y-m-d').' Rechnung Installateur.pdf"', $upload->body());
        $this->assertStringContainsString('MYCZ Dokument #'.$doc->id, $upload->body());
    }

    public function test_inbox_upload_goes_to_eingang(): void
    {
        $this->fakeDrive();
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, '%PDF-1.4 inbox');

        $doc = app(DocumentStorage::class)->store($path, 'HV 2025.pdf', ['doc_type' => DocumentType::AnnualStatement], toInbox: true);

        $this->assertSame(DocumentStatus::Inbox, $doc->status);
        $this->assertContains('_Eingang', collect($this->requests)->filter(fn (Request $r) => $r->method() === 'POST')->map(fn (Request $r) => $r->data()['name'] ?? null)->all());
    }

    public function test_duplicate_upload_is_rejected(): void
    {
        $this->fakeDrive();
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, 'same content');

        app(DocumentStorage::class)->store($path, 'a.pdf', ['doc_type' => DocumentType::Other]);
        $this->expectExceptionMessage('bereits vorhanden');
        app(DocumentStorage::class)->store($path, 'b.pdf', ['doc_type' => DocumentType::Other]);
    }

    public function test_local_fallback_without_drive(): void
    {
        Storage::fake('local');
        Setting::forget('google.refresh_token');
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, 'local content');

        $doc = app(DocumentStorage::class)->store($path, 'Polizze.pdf', ['doc_type' => DocumentType::InsurancePolicy]);

        $this->assertNull($doc->drive_file_id);
        Storage::disk('local')->assertExists($doc->local_path);
        $this->assertSame(route('documents.download', $doc), $doc->url());
    }

    public function test_import_statements_from_drive_and_move_to_importiert(): void
    {
        $json = file_get_contents(base_path('tests/fixtures/statement.json'));
        $this->fakeDrive(
            files: ['in parents and trashed = false' => [
                ['id' => 'JSON1', 'name' => 'pdf-file-123.json', 'mimeType' => 'application/json'],
                ['id' => 'TXT1', 'name' => 'notiz.txt', 'mimeType' => 'text/plain'],
            ]],
            downloads: ['JSON1' => $json],
        );
        $this->makeProperty(['name' => 'Haus Melk']);

        $result = app(StatementImporter::class)->importFromDrive();

        $this->assertSame(1, $result['imported'], json_encode($result));
        $this->assertSame([], $result['errors']);
        $this->assertTrue(collect($this->requests)->contains(fn (Request $r) => $r->method() === 'PATCH' && str_contains($r->url(), 'files/JSON1?') && str_contains($r->url(), 'addParents=')));

        // Zweiter Lauf: bereits importiert
        $this->assertSame(0, app(StatementImporter::class)->importFromDrive()['imported']);
    }

    public function test_expired_access_token_is_refreshed(): void
    {
        Setting::put('google.access_token', ['token' => 'old', 'expires_at' => time() - 10]);
        $this->fakeDrive();

        app(GoogleDriveService::class)->listChildren('ROOT');

        $this->assertTrue(collect($this->requests)->contains(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com/token')));
        $this->assertSame('new', Setting::get('google.access_token')['token']);
    }

    public function test_query_values_are_escaped(): void
    {
        $this->fakeDrive();
        app(GoogleDriveService::class)->findChild('ROOT', "O'Brien");

        parse_str(parse_url($this->requests[0]->url(), PHP_URL_QUERY), $query);
        $this->assertStringContainsString("name = 'O\\'Brien'", $query['q']);
    }

    public function test_status_export_writes_status_json(): void
    {
        $this->fakeDrive();
        app(StatusExporter::class)->export();

        $upload = collect($this->requests)->first(fn (Request $r) => str_contains($r->url(), 'uploadType=multipart'));
        $this->assertStringContainsString('"name":"status.json"', $upload->body());
        $this->assertStringContainsString('mycz.status.v1', $upload->body());
        $this->assertNotNull(Setting::get('drive.status_exported_at'));
    }

    public function test_backup_excludes_secrets(): void
    {
        $this->fakeDrive();
        $this->makeLease();
        $service = app(BackupService::class);

        $this->assertTrue($service->isDue());
        $data = $service->build();
        $this->assertCount(1, $data['tables']['leases']);
        $this->assertArrayNotHasKey('settings', $data['tables']);
        $this->assertArrayNotHasKey('password', $data['tables']['users'][0] ?? []);

        $service->export();
        $this->assertFalse($service->isDue());
        $upload = collect($this->requests)->first(fn (Request $r) => str_contains($r->url(), 'uploadType=multipart'));
        $this->assertStringContainsString('mycz-backup-', $upload->body());
        $this->assertStringNotContainsString('refresh', $upload->body());
    }
}
