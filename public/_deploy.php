<?php

/**
 * Entpackt das von GitHub Actions hochgeladene release.zip am Webspace.
 *
 * Bewusst ohne Laravel, damit es auch bei unvollständiger oder kaputter
 * Installation funktioniert. Aufruf nur per POST mit dem Header
 * X-Deploy-Token (Wert wie DEPLOY_TOKEN in ../.env).
 */
header('Content-Type: application/json');

$root = dirname(__DIR__);
$archive = $root.'/release.zip';

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function envValue(string $file, string $key): ?string
{
    if (! is_readable($file)) {
        return null;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === $key) {
            return trim($value, "\"'");
        }
    }

    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'POST erwartet']);
}

$token = (string) envValue($root.'/.env', 'DEPLOY_TOKEN');
$given = (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');
if (strlen($token) < 32 || ! hash_equals($token, $given)) {
    respond(403, ['ok' => false, 'error' => 'Token fehlt oder falsch']);
}

if (! is_file($archive)) {
    respond(404, ['ok' => false, 'error' => 'release.zip nicht gefunden']);
}

ignore_user_abort(true);
@set_time_limit(300);

try {
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            respond(500, ['ok' => false, 'error' => 'release.zip ist beschädigt']);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, '/') || in_array('..', explode('/', str_replace('\\', '/', $name)), true)) {
                respond(400, ['ok' => false, 'error' => 'Ungültiger Pfad im Archiv: '.$name]);
            }
        }
        $count = $zip->numFiles;
        if (! $zip->extractTo($root)) {
            respond(500, ['ok' => false, 'error' => 'Entpacken fehlgeschlagen (Schreibrechte?)']);
        }
        $zip->close();
    } elseif (class_exists(PharData::class)) {
        $phar = new PharData($archive);
        $count = iterator_count(new RecursiveIteratorIterator($phar));
        $phar->extractTo($root, null, true);
    } else {
        respond(500, ['ok' => false, 'error' => 'Weder ZipArchive noch PharData verfügbar. PHP-Erweiterung "zip" im World4You-Kundenbereich aktivieren.']);
    }
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => $e->getMessage()]);
}

@unlink($archive);

respond(200, ['ok' => true, 'files' => $count]);
