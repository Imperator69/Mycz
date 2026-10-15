<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

/**
 * public/_deploy.php laeuft am Server ohne Laravel. Getestet wird es daher
 * mit dem PHP-Builtin-Server gegen ein temporaeres Verzeichnis.
 */
class DeployScriptTest extends TestCase
{
    private string $root;

    private Process $server;

    private string $url;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/mycz-deploy-'.uniqid();
        File::ensureDirectoryExists($this->root.'/public');
        File::copy(base_path('public/_deploy.php'), $this->root.'/public/_deploy.php');

        $this->token = str_repeat('t', 40);
        File::put($this->root.'/.env', "APP_NAME=\"MYCZ\"\nDEPLOY_TOKEN=\"{$this->token}\"\n");
        File::put($this->root.'/keep.txt', 'bleibt');

        $port = 18000 + random_int(0, 999);
        $this->url = "http://127.0.0.1:$port/_deploy.php";
        $this->server = new Process([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $this->root.'/public']);
        $this->server->start();
        usleep(400000);
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function zip(array $files): void
    {
        $zip = new ZipArchive;
        $zip->open($this->root.'/release.zip', ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
    }

    private function deploy(?string $token): Response
    {
        return Http::withHeaders(array_filter(['X-Deploy-Token' => $token]))->post($this->url);
    }

    public function test_extracts_release_and_keeps_env(): void
    {
        $this->zip([
            'app/Models/Foo.php' => '<?php // neu',
            'public/index.php' => '<?php echo "ok";',
            'vendor/x/y..z.php' => 'zwei Punkte im Namen sind erlaubt',
        ]);

        $response = $this->deploy($this->token);

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue($response->json('ok'));
        $this->assertSame(3, $response->json('files'));
        $this->assertFileExists($this->root.'/app/Models/Foo.php');
        $this->assertFileExists($this->root.'/vendor/x/y..z.php');
        $this->assertFileDoesNotExist($this->root.'/release.zip');
        $this->assertStringContainsString($this->token, File::get($this->root.'/.env'));
        $this->assertSame('bleibt', File::get($this->root.'/keep.txt'));
    }

    public function test_rejects_wrong_or_missing_token(): void
    {
        $this->zip(['a.txt' => 'x']);

        $this->assertSame(403, $this->deploy('falsch')->status());
        $this->assertSame(403, $this->deploy(null)->status());
        $this->assertFileExists($this->root.'/release.zip');
    }

    public function test_rejects_get_and_missing_archive(): void
    {
        $this->assertSame(405, Http::get($this->url)->status());
        $this->assertSame(404, $this->deploy($this->token)->status());
    }

    public function test_rejects_path_traversal(): void
    {
        $this->zip(['../ausserhalb.txt' => 'boese']);

        $this->assertSame(400, $this->deploy($this->token)->status());
        $this->assertFileDoesNotExist(dirname($this->root).'/ausserhalb.txt');
    }
}
