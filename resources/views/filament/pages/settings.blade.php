<x-filament-panels::page>
    <x-filament::section heading="Google Drive" icon="heroicon-o-cloud">
        @if (! $configured)
            <div class="space-y-2 text-sm">
                <p class="font-medium mycz-warn">Noch nicht eingerichtet.</p>
                <p>In der <code>.env</code> am Server <code>GOOGLE_CLIENT_ID</code> und <code>GOOGLE_CLIENT_SECRET</code> eintragen (Anleitung im README, Abschnitt „Google Drive“). Autorisierte Weiterleitungs-URI im Google-Cloud-Projekt:</p>
                <pre class="mycz-pre">{{ $redirectUri }}</pre>
            </div>
        @elseif (! $connected)
            <p class="text-sm">Zugangsdaten vorhanden. Oben auf <strong>Google Drive verbinden</strong> klicken und mit dem Google-Konto anmelden, in dessen Drive archiviert werden soll.</p>
        @else
            <dl class="grid gap-2 text-sm md:grid-cols-2">
                <div><dt class="text-gray-500">Verbundenes Konto</dt><dd class="font-medium">{{ $account ?? 'unbekannt' }}</dd></div>
                <div><dt class="text-gray-500">Root-Ordner</dt><dd>@if ($rootUrl)<a class="text-primary-600 underline" target="_blank" href="{{ $rootUrl }}">Im Drive öffnen</a>@else noch nicht angelegt @endif</dd></div>
                <div><dt class="text-gray-500">Letzte Synchronisation</dt><dd>{{ $lastHousekeeping ? \Illuminate\Support\Carbon::parse($lastHousekeeping)->format('d.m.Y H:i') : '–' }}</dd></div>
                <div><dt class="text-gray-500">status.json zuletzt exportiert</dt><dd>{{ $lastStatusExport ? \Illuminate\Support\Carbon::parse($lastStatusExport)->format('d.m.Y H:i') : '–' }}</dd></div>
                <div><dt class="text-gray-500">Letztes Daten-Backup (wöchentlich, _System/backups)</dt><dd>{{ $lastBackup ? \Illuminate\Support\Carbon::parse($lastBackup)->format('d.m.Y H:i') : '–' }}</dd></div>
            </dl>
        @endif
    </x-filament::section>

    <x-filament::section heading="Ordnerstruktur im Drive" icon="heroicon-o-folder" collapsible>
<pre class="text-xs leading-5">Immobilien/
  &lt;Objekt #ID&gt;/
    &lt;Jahr&gt;/Betriebskosten | Rechnungen | Steuer | Fotos | Korrespondenz | Sonstiges
    Dauerhaft/Kauf und Grundbuch | Mietvertraege | Versicherung | Kredit | Protokolle | Plaene
    &lt;Einheit #ID&gt;/ ...      (Einheiten eines Hauses liegen im Hausordner)
  _Allgemein/              (Dokumente ohne Objekt)
  _Eingang/                (Abrechnungen zur Auswertung durch Claude)
  _System/status.json      (App → Claude: offene Mieten, Fristen, Kennzahlen)
  _System/auswertungen/    (Claude → App: Auswertungen als JSON)
  _System/backups/         (wöchentliches Daten-Backup der App)</pre>
    </x-filament::section>

    <x-filament::section heading="Monatlicher Claude-Reminder" icon="heroicon-o-envelope" collapsible>
        <div class="space-y-2 text-sm">
            <p>Ein geplanter Claude-Task (Connectors: Google Drive, Gmail) liest monatlich <code>_System/status.json</code>, wertet neue PDFs in <code>_Eingang</code> aus, legt die Ergebnisse als JSON in <code>_System/auswertungen</code> ab und schickt eine Reminder-Mail mit offenen Mieten, Fristen und Monats-Summary.</p>
            <p>Die Auswertungen werden hier unter <strong>Finanzen → Jahresabrechnungen → Auswertungen aus Drive holen</strong> importiert, geprüft und gebucht. Prompt und Schema: <code>docs/claude-monthly-task.md</code> und <code>docs/statement-schema.json</code> im Repository.</p>
            <p>Die App aktualisiert <code>status.json</code> automatisch bei Nutzung (höchstens alle 6 Stunden) oder über „Jetzt synchronisieren“.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
