<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Exportiert alle Fachdaten als JSON ins Drive (_System/backups).
 * Ohne Sessions, Caches, OAuth-Tokens und Passwort-Hashes.
 */
class BackupService
{
    public const TABLES = [
        'properties', 'tenants', 'leases', 'rent_charges', 'categories', 'transactions', 'documents',
        'statements', 'statement_lines', 'loans', 'contacts', 'contact_property', 'insurance_policies',
        'insurance_claims', 'contracts', 'maintenance_tasks', 'meters', 'meter_readings', 'tickets', 'deadlines',
    ];

    public function __construct(private readonly GoogleDriveService $drive) {}

    public function build(): array
    {
        $data = ['schema' => 'mycz.backup.v1', 'created_at' => now()->toIso8601String(), 'tables' => []];
        foreach (self::TABLES as $table) {
            $data['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        $data['tables']['users'] = DB::table('users')->get(['id', 'name', 'email', 'created_at'])->map(fn ($row) => (array) $row)->all();

        return $data;
    }

    public function export(): string
    {
        $json = json_encode($this->build(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $folder = $this->drive->ensurePath($this->drive->systemId(), ['backups']);
        $id = $this->drive->putFile($folder, 'mycz-backup-'.now()->format('Y-m-d').'.json', $json, 'application/json');
        Setting::put('backup.last_run', now()->toIso8601String());

        return $id;
    }

    public function isDue(): bool
    {
        $last = Setting::get('backup.last_run');

        return ! $last || now()->diffInDays(Carbon::parse($last)) >= 7;
    }
}
