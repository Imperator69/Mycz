<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_missing_or_wrong_token(): void
    {
        config(['services.deploy.token' => str_repeat('a', 40)]);
        $this->postJson('/system/deploy')->assertForbidden();
        $this->postJson('/system/deploy', [], ['X-Deploy-Token' => 'falsch'])->assertForbidden();
    }

    public function test_rejects_when_token_too_short(): void
    {
        config(['services.deploy.token' => 'kurz']);
        $this->postJson('/system/deploy', [], ['X-Deploy-Token' => 'kurz'])->assertForbidden();
    }

    public function test_migrates_and_creates_first_admin_once(): void
    {
        $token = str_repeat('x', 40);
        config(['services.deploy' => ['token' => $token, 'admin_name' => 'Owner', 'admin_email' => 'owner@example.com', 'admin_password' => 'very-secret-pass']]);

        $this->postJson('/system/deploy', [], ['X-Deploy-Token' => $token])->assertOk()->assertJson(['ok' => true, 'admin' => 'created']);
        $this->assertSame(1, User::count());

        $this->postJson('/system/deploy', [], ['X-Deploy-Token' => $token])->assertOk()->assertJsonMissing(['admin' => 'created']);
        $this->assertSame(1, User::count());
    }
}
