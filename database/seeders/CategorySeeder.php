<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Standardkategorien. "is_allocatable" = typischerweise auf Mieter
 * umlagefaehig (Vorschlag, je Buchung aenderbar). "affects_result" = zaehlt
 * ins Ergebnis (Tilgung und Investitionen nur in den Cashflow).
 */
class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        // slug, name, type, group, allocatable, affects_result
        ['miete', 'Mieteinnahmen', 'income', 'Mieten', false, true],
        ['bk-nachzahlung-mieter', 'BK-Nachzahlung Mieter', 'income', 'Mieten', false, true],
        ['gutschrift', 'Guthaben / Gutschriften', 'income', 'Sonstiges', false, true],
        ['versicherungsleistung', 'Versicherungsleistung', 'income', 'Sonstiges', false, true],
        ['foerderung', 'Förderungen / Zuschüsse', 'income', 'Sonstiges', false, true],
        ['kaution-erhalten', 'Kaution erhalten', 'income', 'Kaution', false, false],
        ['sonstige-einnahmen', 'Sonstige Einnahmen', 'income', 'Sonstiges', false, true],

        ['betriebskosten', 'Betriebskosten (Hausverwaltung)', 'expense', 'Betriebskosten', true, true],
        ['wasser-kanal-muell', 'Wasser / Kanal / Müll', 'expense', 'Betriebskosten', true, true],
        ['allgemeinstrom', 'Allgemeinstrom / Beleuchtung', 'expense', 'Betriebskosten', true, true],
        ['heizung', 'Heizung / Warmwasser', 'expense', 'Betriebskosten', true, true],
        ['hausbetreuung', 'Hausbetreuung / Reinigung', 'expense', 'Betriebskosten', true, true],
        ['grundsteuer', 'Grundsteuer', 'expense', 'Steuern und Abgaben', true, true],
        ['versicherung', 'Versicherungsprämien', 'expense', 'Versicherung', true, true],
        ['verwaltung', 'Verwaltungshonorar', 'expense', 'Verwaltung', true, true],
        ['reparaturfonds', 'Reparaturfonds / Rücklage', 'expense', 'Instandhaltung', false, true],
        ['instandhaltung', 'Instandhaltung / Reparaturen', 'expense', 'Instandhaltung', false, true],
        ['wartung', 'Wartung / Überprüfung', 'expense', 'Instandhaltung', false, true],
        ['kreditzinsen', 'Kreditzinsen', 'expense', 'Finanzierung', false, true],
        ['bankspesen', 'Bankspesen', 'expense', 'Finanzierung', false, true],
        ['tilgung', 'Kredittilgung', 'expense', 'Finanzierung', false, false],
        ['investition', 'Investition / Anschaffung', 'expense', 'Investition', false, false],
        ['beratung', 'Steuerberatung / Rechtsberatung', 'expense', 'Verwaltung', false, true],
        ['energie-leerstand', 'Energie bei Leerstand / Eigennutzung', 'expense', 'Betriebskosten', false, true],
        ['kaution-rueckzahlung', 'Kaution zurückgezahlt', 'expense', 'Kaution', false, false],
        ['sonstige-ausgaben', 'Sonstige Ausgaben', 'expense', 'Sonstiges', false, true],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => [$slug, $name, $type, $group, $allocatable, $result]) {
            Category::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'type' => $type,
                'group' => $group,
                'is_allocatable' => $allocatable,
                'affects_result' => $result,
                'sort' => $i,
            ]);
        }
    }
}
