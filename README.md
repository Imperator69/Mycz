# MYCZ Immobilienverwaltung

Verwaltung der eigenen Wohnungen, Häuser, Garagen und Grundstücke unter **https://mycz.gl-ventures.at**.

- **Bestand:** Objekte (inkl. Häuser mit Einheiten), Grundbuch und Kaufdaten, Mieter, Mietverträge mit Kaution und Wertsicherung
- **Finanzen:** monatliche Soll-Mieten mit manuellem Abhaken, Einnahmen und Ausgaben je Objekt, Kredite mit Tilgungsplan, Jahresabrechnungen
- **Verwaltung:** Versicherungen (Polizzen, Ablauf, Kündigungsfristen, Schadensfälle), Verträge, Dienstleister, Wartung, Zähler, Schäden und Aufgaben, eigene Erinnerungen
- **Fristenkalender:** sammelt automatisch alle Termine aus allen Modulen
- **Auswertungen:** Dashboard, Ergebnis und Cashflow, Rendite brutto und netto, Kategorien, Monatsverlauf, Jahresvergleich, Objektvergleich, Export als PDF und CSV für den Steuerberater
- **Google Drive:** Dokumente werden automatisch in `Immobilien/<Objekt>/<Jahr>/<Kategorie>` abgelegt, wöchentliches Daten-Backup
- **Claude:** wertet monatlich neue Abrechnungen aus und schickt per Gmail einen Reminder mit offenen Mieten und Fristen

**Laufende Zusatzkosten: 0 €.** Die App läuft auf dem bestehenden World4You-Webspace (PHP und MySQL). Die Google-Drive-API ist kostenlos. Claude arbeitet im bestehenden Claude-Abo. Eine OpenAI- oder Claude-API wird nicht verwendet.

## Architektur

```
Browser ──► mycz.gl-ventures.at  (Laravel 12 + Filament 3, MySQL, World4You)
                 │  Drive-API (OAuth, eigenes Google-Konto)
                 ▼
           Google Drive: Immobilien/
             <Objekt #ID>/<Jahr>/<Kategorie>/   Archiv
             <Objekt #ID>/Dauerhaft/            Kaufvertrag, Grundbuch, Mietverträge, Polizzen
             _Eingang/                          neue Abrechnungen
             _System/status.json                App → Claude
             _System/auswertungen/*.json        Claude → App
             _System/backups/                   wöchentliches Daten-Backup
                 ▲
                 │  Connectors Google Drive + Gmail
           Claude, geplante Aufgabe (monatlich)
```

App und Claude tauschen Daten nur über Drive aus. Claude braucht daher keinen Zugang zur App und die App keinen KI-API-Key.

Auf dem Webspace ist kein Cron nötig. Soll-Mieten, `status.json` und das Backup werden bei Nutzung der App erzeugt (höchstens alle 6 Stunden, nach Auslieferung der Seite). Zusätzlich gibt es unter Einstellungen den Button „Jetzt synchronisieren“.

## Einrichtung

### 1. World4You

1. Im World4You-Kundenbereich die Subdomain **mycz.gl-ventures.at** anlegen und mit SSL (Let's Encrypt) versehen.
2. Für die Subdomain **PHP 8.3** (mindestens 8.2) auswählen. Benötigte Erweiterungen: `intl`, `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`.
3. Eine **MySQL-Datenbank** anlegen und Host, Datenbankname, Benutzer und Passwort notieren.
4. Das **Verzeichnis der Subdomain** festlegen, z.B. `/mycz/public`. Kann die Subdomain nur auf `/mycz` zeigen, leitet die mitgelieferte `.htaccess` im Projektstamm automatisch nach `public/` um.
5. **FTP-Zugang** notieren: Server, Benutzer, Passwort, und ob FTPS unterstützt wird.

### 2. `.env` am Server anlegen (einmalig)

1. `.env.example` kopieren und als `.env` ausfüllen: Datenbank, Mail (nur für „Passwort vergessen“), Google, `DEPLOY_TOKEN`, `ADMIN_*`.
2. `APP_KEY` lokal erzeugen: `php artisan key:generate --show`. Ohne lokales PHP geht auch `echo "base64:$(openssl rand -base64 32)"`.
3. `DEPLOY_TOKEN` als Zufallswert mit mindestens 32 Zeichen setzen: `openssl rand -hex 32`.
4. Die Datei per FTP nach `/mycz/.env` hochladen. Sie liegt außerhalb von `public/` und ist nicht über das Web erreichbar.

### 3. GitHub-Deploy

Repository → Settings → Secrets and variables → Actions:

| Typ | Name | Wert |
|---|---|---|
| Secret | `FTP_SERVER` | FTP-Host von World4You |
| Secret | `FTP_USERNAME` | FTP-Benutzer |
| Secret | `FTP_PASSWORD` | FTP-Passwort |
| Secret | `DEPLOY_TOKEN` | derselbe Wert wie in der `.env` |
| Variable (optional) | `FTP_SERVER_DIR` | Zielordner, Standard `/mycz/` |
| Variable (optional) | `FTP_PROTOCOL` | `ftps` (Standard) oder `ftp` |
| Variable (optional) | `APP_URL` | Standard `https://mycz.gl-ventures.at` |

Jeder Push auf `main` (oder manuell: Actions → „Deploy World4You“ → Run workflow) läuft so ab:

1. Tests ausführen
2. Produktionsabhängigkeiten installieren
3. Release-Paket `release.zip` bauen
4. FTP-Upload von zwei Dateien: `release.zip` und `public/_deploy.php`
5. Aufruf von `POST /_deploy.php`: entpackt das Paket am Server
6. Aufruf von `POST /system/deploy`: Datenbank-Migrationen

Ein ZIP statt tausender Einzeldateien hält den Upload kurz und robust gegen Verbindungsabbrüche. `_deploy.php` läuft ohne Laravel und nur mit dem `DEPLOY_TOKEN` aus der `.env`. Die `.env` und hochgeladene Dokumente in `storage/` sind nicht im Paket und bleiben unverändert. Am Server muss die PHP-Erweiterung `zip` aktiv sein; fehlt sie, meldet der Schritt „Am Server entpacken“ das.

Der Deploy-Hook führt die Datenbank-Migrationen aus, legt die Standardkategorien an und leert die Caches. Beim ersten Mal legt er außerdem den Benutzer aus `ADMIN_*` an. Danach `ADMIN_PASSWORD` aus der `.env` am Server entfernen. Weitere Benutzer legt ihr in der App unter System → Benutzer an.

### 4. Google Drive (kostenlos)

1. https://console.cloud.google.com → neues Projekt „MYCZ“. Ein Rechnungskonto ist nicht nötig.
2. APIs & Dienste → Bibliothek → **Google Drive API** aktivieren.
3. OAuth-Zustimmungsbildschirm einrichten:
   - Mit Google Workspace (z.B. Konto @gl-ventures.at): Nutzertyp **Intern**. Damit ist keine Überprüfung durch Google nötig und der Zugriff läuft nicht ab.
   - Mit privatem Gmail-Konto: Nutzertyp **Extern**, eigenes Konto als Testnutzer eintragen und danach **„App veröffentlichen“** (Produktion). Wichtig: Im Status „Testing“ laufen Googles Refresh-Tokens nach 7 Tagen ab und Drive müsste wöchentlich neu verbunden werden. Nach dem Veröffentlichen zeigt Google beim Verbinden den Hinweis „App nicht überprüft“. Für die eigene Nutzung kann man über „Erweitert“ fortfahren.
4. Anmeldedaten → **OAuth-Client-ID** → Typ „Webanwendung“ → Autorisierte Weiterleitungs-URI: `https://mycz.gl-ventures.at/google/callback`
5. Client-ID und Secret als `GOOGLE_CLIENT_ID` und `GOOGLE_CLIENT_SECRET` in die `.env` am Server eintragen.
6. In der App: System → Einstellungen → **Google Drive verbinden** und mit dem Konto anmelden, in dessen Drive archiviert werden soll. Die App legt `Immobilien/`, `_Eingang/` und `_System/` an. Ein bestehender Ordner lässt sich über „Drive-Aktionen → Ordnerstruktur anlegen“ mit seiner ID übernehmen.

Die App fordert den Scope `drive` an, weil sie auch Dateien lesen muss, die Claude oder ihr selbst ins Drive legt.

### 5. Monatlicher Claude-Reminder

1. In claude.ai unter Einstellungen → Connectors **Google Drive** und **Gmail** verbinden, mit dem Google-Konto aus Schritt 4.
2. Eine geplante Aufgabe anlegen. Den Prompt aus [`docs/claude-monthly-task.md`](docs/claude-monthly-task.md) verwenden und `<EMPFAENGER>` ersetzen. Zeitplan z.B. am 5. jedes Monats um 07:52 Uhr (Europe/Vienna). Alternativ Claude im Chat bitten, die Aufgabe mit diesem Prompt anzulegen.
3. Die Aufgabe einmal manuell starten und den Mail-Eingang prüfen.

Ablauf pro Monat: Claude liest `status.json`, wertet neue PDFs in `_Eingang/` nach [`docs/statement-schema.json`](docs/statement-schema.json) aus, legt die JSON-Dateien in `_System/auswertungen/` ab und schickt die Mail. In der App unter Finanzen → Jahresabrechnungen → „Auswertungen aus Drive holen“ werden sie importiert, geprüft und gebucht.

## Bedienung

| Aufgabe | Wo |
|---|---|
| Neues Objekt anlegen (Drive-Ordner entsteht automatisch) | Bestand → Objekte → Neues Objekt |
| Haus mit Wohnungen | Haus anlegen, im Haus Reiter „Einheiten“ → Neue Einheit |
| Mieter und Vertrag | Bestand → Mietverträge → Neuer Mietvertrag. Ab „Soll-Mieten erfassen ab“ entstehen monatliche Soll-Positionen. |
| Mieteingänge kontrollieren | Finanzen → Mieteingänge: „Bezahlt“, Teilzahlung, Mahnstufe; mehrere auf einmal per Auswahl abhaken. Bezahlt erzeugt automatisch die Einnahme-Buchung. |
| Indexanpassung | Mietvertrag → „Miete anpassen“ (offene Soll-Positionen ab dem Monat werden mit angepasst) |
| Kosten erfassen | Finanzen → Einnahmen & Ausgaben → Neue Buchung (Brutto und USt-Satz, Netto wird berechnet) |
| Jahresabrechnung | Finanzen → Jahresabrechnungen → Abrechnung hochladen (landet in `_Eingang/`). Nach dem Claude-Lauf „Auswertungen aus Drive holen“, prüfen, dann „Positionen buchen“ (wenn die monatlichen HV-Vorschreibungen nicht gebucht werden) oder „Nur Saldo buchen“ (wenn sie laufend gebucht werden). Das PDF wird danach ins Objektarchiv verschoben. |
| Auswertung ohne geplanten Task | PDF im Claude-Chat hochladen, Schema aus `docs/statement-schema.json` mitgeben, das JSON unter Jahresabrechnungen → „JSON manuell importieren“ einfügen |
| Kredit-Zinsen und Tilgung buchen | Finanzen → Kredite → Kredit → „Jahreswerte buchen“ |
| Fristen | Übersicht → Fristen (Versicherungsablauf und Kündigungsfrist, Befristungen, Wertsicherung, Zinsbindung, Wartung, eigene Erinnerungen) |
| Bericht für Steuerberater | Übersicht → Auswertungen → Exportieren (PDF-Jahresbericht, Kategorie-Summen CSV, Buchungsliste CSV), je Objekt oder gesamt |

Begriffe in den Auswertungen:

- **Ergebnis:** Einnahmen minus Ausgaben, ohne Kategorien mit „Zählt ins Ergebnis = aus“ (Tilgung, Investition, Kautionen).
- **Cashflow:** alle Zahlungen.
- **Bruttorendite:** Hauptmietzins p.a. / (Kaufpreis + Nebenkosten).
- **Nettorendite:** (Einnahmen - nicht umlagefähige Ausgaben) / (Kaufpreis + Nebenkosten).

Die Exporte sind Einnahmen-Ausgaben-Aufstellungen. Sie enthalten keine Steuerberechnung und keine AfA.

## Datenschutz und Sicherheit

- Login für alle Seiten. Neue Benutzer brauchen ein Passwort mit mindestens 10 Zeichen. Sessions sind verschlüsselt und laufen nur über HTTPS.
- Google-Tokens liegen verschlüsselt (APP_KEY) in der Datenbank.
- `status.json` für Claude enthält Objektnamen, Mieternamen und Beträge, aber keine IBANs, Kontaktdaten oder Geburtsdaten.
- Das Aktivitätslog (System → Aktivitätslog) zeigt, wer was geändert hat.
- Backups: wöchentlich als JSON nach `_System/backups/` (ohne Passwörter und Tokens). Zusätzlich sind die MySQL-Sicherungen von World4You verfügbar.
- Wird ein Dokument in der App gelöscht, bleibt die Datei im Drive erhalten.

## Lokale Entwicklung

```bash
composer install
cp .env.example .env    # DB_CONNECTION=sqlite, APP_ENV=local, APP_DEBUG=true setzen
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=DemoSeeder   # Login: demo@example.com / demo-passwort
php artisan serve                        # http://127.0.0.1:8000/admin
php artisan test
vendor/bin/pint
```

Der Code liegt vor allem in:

- `app/Services/`: Fachlogik (RentService, DeadlineAggregator, ReportBuilder, GoogleDriveService, DocumentStorage, StatementImporter, StatusExporter, BackupService, LoanSchedule)
- `app/Filament/`: Oberfläche
- `tests/Feature/`: Tests (Drive über HTTP-Fakes)
