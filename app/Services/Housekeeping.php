<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ersetzt einen Server-Cron: wird bei App-Nutzung (hoechstens alle paar
 * Stunden) nach dem Ausliefern der Antwort ausgefuehrt.
 */
class Housekeeping
{
    public function __construct(
        private readonly RentService $rents,
        private readonly GoogleDriveService $drive,
        private readonly StatusExporter $status,
        private readonly BackupService $backup,
    ) {}

    public function runIfDue(): void
    {
        if (! Cache::add('housekeeping.lock', true, now()->addHours(6))) {
            return;
        }

        try {
            $this->run();
        } catch (\Throwable $e) {
            Log::warning('Housekeeping fehlgeschlagen: '.$e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $result = ['charges_created' => $this->rents->generateCharges()];

        if ($this->drive->isConnected()) {
            try {
                $this->status->export();
                $result['status_exported'] = true;
            } catch (\Throwable $e) {
                $result['status_exported'] = false;
                $result['status_error'] = GoogleDriveService::describeError($e);
            }

            if ($this->backup->isDue()) {
                try {
                    $this->backup->export();
                    $result['backup'] = true;
                } catch (\Throwable $e) {
                    $result['backup'] = false;
                }
            }
        }

        Setting::put('housekeeping.last_run', now()->toIso8601String());

        return $result;
    }
}
