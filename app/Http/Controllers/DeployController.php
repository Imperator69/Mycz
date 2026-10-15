<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Wird nach jedem FTP-Deploy von GitHub Actions aufgerufen, da auf dem
 * Webspace kein SSH vorausgesetzt wird: Migrationen ausfuehren, Caches
 * leeren und beim ersten Mal den Admin-Benutzer anlegen.
 */
class DeployController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('services.deploy.token');
        if (strlen($token) < 32 || ! hash_equals($token, (string) $request->header('X-Deploy-Token'))) {
            abort(403);
        }

        $output = [];
        Artisan::call('migrate', ['--force' => true]);
        $output['migrate'] = trim(Artisan::output());

        Artisan::call('optimize:clear');
        $output['optimize'] = trim(Artisan::output());

        $admin = config('services.deploy');
        if (User::count() === 0 && filled($admin['admin_email']) && filled($admin['admin_password'])) {
            User::create([
                'name' => $admin['admin_name'],
                'email' => $admin['admin_email'],
                'password' => Hash::make($admin['admin_password']),
            ]);
            $output['admin'] = 'created';
        }

        return response()->json(['ok' => true] + $output);
    }
}
