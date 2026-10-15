<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Schlanker Google-Drive-v3-Client ueber die REST-API (OAuth 2.0 mit
 * Refresh-Token des eigenen Google-Kontos). Kein Google-SDK noetig.
 */
class GoogleDriveService
{
    public const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public const SCOPE = 'https://www.googleapis.com/auth/drive';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3';

    // ---------------------------------------------------------------- OAuth

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->isConfigured() && filled(Setting::get('google.refresh_token'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect') ?: url('/google/callback');
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function handleCallback(string $code): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($response['refresh_token'])) {
            throw new RuntimeException('Google hat kein Refresh-Token geliefert. Zugriff unter myaccount.google.com/permissions entfernen und erneut verbinden.');
        }

        Setting::put('google.refresh_token', $response['refresh_token']);
        $this->storeAccessToken($response);

        $about = $this->http()->get(self::API.'/about', ['fields' => 'user(emailAddress)'])->json();
        Setting::put('google.account', $about['user']['emailAddress'] ?? null);
    }

    public function disconnect(): void
    {
        Setting::forget('google.refresh_token');
        Setting::forget('google.access_token');
        Setting::forget('google.account');
        Setting::forget('drive.root_id');
        $this->forgetFolderCache();
    }

    public function connectedAccount(): ?string
    {
        return Setting::get('google.account');
    }

    private function accessToken(): string
    {
        $token = Setting::get('google.access_token');
        if ($token && ($token['expires_at'] ?? 0) > time() + 60) {
            return $token['token'];
        }

        $refresh = Setting::get('google.refresh_token');
        if (! $refresh) {
            throw new RuntimeException('Google Drive ist nicht verbunden.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Google-Token konnte nicht erneuert werden ('.$response->json('error', $response->status()).'). Bitte Drive in den Einstellungen neu verbinden.');
        }

        return $this->storeAccessToken($response->json());
    }

    private function storeAccessToken(array $response): string
    {
        Setting::put('google.access_token', [
            'token' => $response['access_token'],
            'expires_at' => time() + (int) ($response['expires_in'] ?? 3600),
        ]);

        return $response['access_token'];
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->accessToken())->timeout(60)->retry(2, 500, throw: false);
    }

    // ---------------------------------------------------------------- Dateien

    /** @return array<int, array<string, mixed>> */
    public function listFiles(string $q, string $fields = 'id,name,mimeType,modifiedTime,createdTime,webViewLink,parents,size,description'): array
    {
        $files = [];
        $pageToken = null;
        do {
            $response = $this->http()->get(self::API.'/files', array_filter([
                'q' => $q,
                'fields' => "nextPageToken,files($fields)",
                'pageSize' => 200,
                'pageToken' => $pageToken,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'orderBy' => 'createdTime',
            ]))->throw()->json();
            $files = array_merge($files, $response['files'] ?? []);
            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken);

        return $files;
    }

    public function listChildren(string $folderId, ?string $mime = null): array
    {
        $q = sprintf("'%s' in parents and trashed = false", $this->escape($folderId));
        if ($mime) {
            $q .= sprintf(" and mimeType = '%s'", $this->escape($mime));
        }

        return $this->listFiles($q);
    }

    public function search(string $term): array
    {
        $q = sprintf("name contains '%s' and trashed = false and mimeType != '%s'", $this->escape($term), self::FOLDER_MIME);

        return array_slice($this->listFiles($q), 0, 50);
    }

    public function getFile(string $fileId): array
    {
        return $this->http()->get(self::API.'/files/'.$fileId, [
            'fields' => 'id,name,mimeType,webViewLink,parents,size,description',
            'supportsAllDrives' => 'true',
        ])->throw()->json();
    }

    public function findChild(string $parentId, string $name, ?string $mime = null): ?array
    {
        $q = sprintf("'%s' in parents and name = '%s' and trashed = false", $this->escape($parentId), $this->escape($name));
        if ($mime) {
            $q .= sprintf(" and mimeType = '%s'", $this->escape($mime));
        }

        return $this->listFiles($q)[0] ?? null;
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
        return $this->http()->post(self::API.'/files?supportsAllDrives=true&fields=id', array_filter([
            'name' => $name,
            'mimeType' => self::FOLDER_MIME,
            'parents' => $parentId ? [$parentId] : null,
        ]))->throw()->json('id');
    }

    /** Ordnerpfad unterhalb von $parentId anlegen bzw. finden (z.B. ['2026', 'Betriebskosten']). */
    public function ensurePath(string $parentId, array $segments): string
    {
        $current = $parentId;
        $map = Cache::get('drive.folders', []);
        foreach ($segments as $segment) {
            $key = $current.'/'.$segment;
            if (! isset($map[$key])) {
                $map[$key] = $this->findChild($current, $segment, self::FOLDER_MIME)['id'] ?? $this->createFolder($segment, $current);
                Cache::forever('drive.folders', $map);
            }
            $current = $map[$key];
        }

        return $current;
    }

    public function forgetFolderCache(): void
    {
        Cache::forget('drive.folders');
    }

    /** @return array{id: string, webViewLink: ?string} */
    public function upload(string $contents, string $name, string $mime, string $parentId, ?string $description = null): array
    {
        $boundary = 'mycz'.Str::random(24);
        $meta = json_encode(array_filter([
            'name' => $name,
            'parents' => [$parentId],
            'description' => $description,
        ]));

        $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
            ."--$boundary\r\nContent-Type: $mime\r\n\r\n$contents\r\n--$boundary--";

        return $this->http()
            ->withBody($body, "multipart/related; boundary=$boundary")
            ->post(self::UPLOAD.'/files?uploadType=multipart&supportsAllDrives=true&fields=id,webViewLink')
            ->throw()
            ->json();
    }

    public function updateContents(string $fileId, string $contents, string $mime): void
    {
        $this->http()
            ->withBody($contents, $mime)
            ->patch(self::UPLOAD.'/files/'.$fileId.'?uploadType=media&supportsAllDrives=true')
            ->throw();
    }

    /** Datei per Namen im Ordner anlegen oder ueberschreiben. */
    public function putFile(string $parentId, string $name, string $contents, string $mime): string
    {
        $existing = $this->findChild($parentId, $name);
        if ($existing) {
            $this->updateContents($existing['id'], $contents, $mime);

            return $existing['id'];
        }

        return $this->upload($contents, $name, $mime, $parentId)['id'];
    }

    public function download(string $fileId): string
    {
        return $this->http()->get(self::API.'/files/'.$fileId, ['alt' => 'media', 'supportsAllDrives' => 'true'])->throw()->body();
    }

    public function move(string $fileId, string $newParentId): void
    {
        $file = $this->getFile($fileId);
        $this->http()->patch(self::API.'/files/'.$fileId.'?'.http_build_query([
            'addParents' => $newParentId,
            'removeParents' => implode(',', $file['parents'] ?? []),
            'supportsAllDrives' => 'true',
        ]), [])->throw();
    }

    public function rename(string $fileId, string $name): void
    {
        $this->http()->patch(self::API.'/files/'.$fileId.'?supportsAllDrives=true', ['name' => $name])->throw();
    }

    // ---------------------------------------------------------------- Struktur

    public function rootId(): string
    {
        $root = Setting::get('drive.root_id');
        if ($root) {
            return $root;
        }

        $name = config('services.google.root_folder', 'Immobilien');
        $existing = $this->listFiles(sprintf("name = '%s' and mimeType = '%s' and 'root' in parents and trashed = false", $this->escape($name), self::FOLDER_MIME))[0] ?? null;
        $root = $existing['id'] ?? $this->createFolder($name);
        Setting::put('drive.root_id', $root);

        return $root;
    }

    public function inboxId(): string
    {
        return $this->ensurePath($this->rootId(), ['_Eingang']);
    }

    public function systemId(): string
    {
        return $this->ensurePath($this->rootId(), ['_System']);
    }

    public function statementsOutboxId(): string
    {
        return $this->ensurePath($this->rootId(), ['_System', 'auswertungen']);
    }

    public function statementsImportedId(): string
    {
        return $this->ensurePath($this->rootId(), ['_System', 'auswertungen', 'importiert']);
    }

    /** Grundstruktur anlegen (Root, Eingang, System). */
    public function bootstrap(): array
    {
        return [
            'root' => $this->rootId(),
            'inbox' => $this->inboxId(),
            'system' => $this->systemId(),
            'outbox' => $this->statementsOutboxId(),
        ];
    }

    public function folderUrl(string $id): string
    {
        return 'https://drive.google.com/drive/folders/'.$id;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    public static function describeError(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return $e->response->json('error.message') ?? $e->getMessage();
        }

        return $e->getMessage();
    }
}
