<?php

namespace App\Http\Controllers;

use App\Filament\Pages\Settings;
use App\Services\GoogleDriveService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleDriveController extends Controller
{
    public function connect(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        abort_unless($drive->isConfigured(), 400, 'GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET fehlen in der .env');

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($drive->authUrl($state));
    }

    public function callback(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        $expected = $request->session()->pull('google_oauth_state');
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            abort(403, 'Ungültiger OAuth-Status.');
        }

        if ($request->filled('error')) {
            Notification::make()->danger()->title('Google-Verbindung abgebrochen')->body($request->query('error'))->send();

            return redirect(Settings::getUrl());
        }

        try {
            $drive->handleCallback((string) $request->query('code'));
            $drive->bootstrap();
            Notification::make()->success()->title('Google Drive verbunden')->body('Konto: '.$drive->connectedAccount())->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Google Drive Fehler')->body(GoogleDriveService::describeError($e))->send();
        }

        return redirect(Settings::getUrl());
    }
}
