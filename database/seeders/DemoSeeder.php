<?php

namespace Database\Seeders;

use App\Enums\ContractType;
use App\Enums\InsuranceLine;
use App\Enums\MeterType;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Deadline;
use App\Models\InsurancePolicy;
use App\Models\Lease;
use App\Models\Loan;
use App\Models\MaintenanceTask;
use App\Models\Meter;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RentService;
use App\Services\StatementImporter;
use Illuminate\Database\Seeder;

/** Beispieldaten fuer lokale Entwicklung: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'demo@example.com'], ['name' => 'Demo', 'password' => bcrypt('demo-passwort')]);

        $house = Property::create(['name' => 'Zinshaus Melk', 'type' => 'house', 'status' => 'rented', 'street' => 'Hauptstraße 12', 'zip' => '3390', 'city' => 'Melk', 'purchase_date' => '2019-05-01', 'purchase_price' => 640000, 'purchase_costs' => 58000, 'area_m2' => 410]);
        $top1 = Property::create(['name' => 'Melk Top 1', 'parent_id' => $house->id, 'type' => 'apartment', 'status' => 'rented', 'unit_number' => '1', 'area_m2' => 72, 'city' => 'Melk']);
        $top2 = Property::create(['name' => 'Melk Top 2', 'parent_id' => $house->id, 'type' => 'apartment', 'status' => 'vacant', 'unit_number' => '2', 'area_m2' => 64, 'city' => 'Melk']);
        $wien = Property::create(['name' => 'Wohnung Wien 7', 'type' => 'apartment', 'status' => 'rented', 'street' => 'Neubaugasse 40/9', 'zip' => '1070', 'city' => 'Wien', 'purchase_price' => 385000, 'purchase_costs' => 36000, 'area_m2' => 68, 'ownership_share' => '68/2140']);
        $garage = Property::create(['name' => 'Garage Wien 7', 'type' => 'garage', 'status' => 'rented', 'city' => 'Wien', 'purchase_price' => 32000]);
        Property::create(['name' => 'Grundstück Krems', 'type' => 'land', 'status' => 'vacant', 'city' => 'Krems', 'land_area_m2' => 980, 'purchase_price' => 95000]);

        $start = today()->subMonths(8)->startOfMonth();
        $mk = fn (Property $p, string $first, string $last, float $rent, float $bk, float $vat) => Lease::create([
            'property_id' => $p->id,
            'tenant_id' => Tenant::create(['first_name' => $first, 'last_name' => $last, 'email' => strtolower($first).'@example.com'])->id,
            'start_date' => $start->copy()->subYear(), 'charges_from' => $start, 'base_rent' => $rent, 'operating_costs' => $bk,
            'vat_rate' => $vat, 'due_day' => 5, 'index_type' => 'vpi2020', 'index_last_adjusted_at' => today()->subMonths(11)->startOfMonth(),
            'deposit_amount' => $rent * 3, 'deposit_type' => 'savings_book',
        ]);
        $mk($top1, 'Lena', 'Gruber', 690, 180, 10);
        $mk($wien, 'Paul', 'Novak', 980, 210, 10);
        $mk($garage, 'Paul', 'Novak', 95, 0, 20);

        $rents = app(RentService::class);
        $rents->generateCharges();
        foreach (RentCharge::where('due_date', '<', today()->subMonth())->get() as $charge) {
            $rents->markPaid($charge, null, $charge->due_date->copy()->subDays(2));
        }

        $cat = fn (string $slug) => Category::bySlug($slug)->id;
        foreach ([
            [$house, 'grundsteuer', 'Grundsteuer Q', 312, 0, -6],
            [$house, 'versicherung', 'Gebäudeversicherung', 1840, 0, -5],
            [$top2, 'instandhaltung', 'Malerarbeiten Top 2', 3600, 20, -3],
            [$wien, 'betriebskosten', 'HV Vorschreibung', 1260, 0, -2],
            [$house, 'kreditzinsen', 'Zinsen Q', 2350, 0, -1],
        ] as [$p, $slug, $desc, $gross, $vat, $months]) {
            Transaction::create(['property_id' => $p->id, 'category_id' => $cat($slug), 'booked_at' => today()->addMonths($months), 'description' => $desc, 'amount_gross' => $gross, 'vat_rate' => $vat, 'is_allocatable' => Category::bySlug($slug)->is_allocatable]);
        }

        $hv = Contact::create(['name' => 'Fr. Huber', 'company' => 'Hausverwaltung Donau GmbH', 'role' => 'Hausverwaltung', 'phone' => '+43 1 234567']);
        $hv->properties()->attach([$wien->id]);
        InsurancePolicy::create(['property_id' => $house->id, 'insurer' => 'Wiener Städtische', 'policy_number' => 'G-55123', 'line' => InsuranceLine::Building, 'coverage_amount' => 1200000, 'premium' => 1840, 'payment_interval' => 'annual', 'start_date' => '2019-06-01', 'end_date' => today()->addMonths(3)->startOfMonth(), 'notice_period_months' => 3]);
        InsurancePolicy::create(['property_id' => $wien->id, 'insurer' => 'Uniqa', 'policy_number' => 'H-88231', 'line' => InsuranceLine::Household, 'premium' => 14.5, 'payment_interval' => 'monthly', 'end_date' => today()->addMonths(7), 'notice_period_months' => 1]);
        Contract::create(['property_id' => $wien->id, 'contact_id' => $hv->id, 'type' => ContractType::PropertyManagement, 'provider' => 'Hausverwaltung Donau GmbH', 'cost_amount' => 0, 'cost_interval' => 'monthly']);
        Contract::create(['property_id' => $house->id, 'type' => ContractType::Electricity, 'provider' => 'EVN', 'cost_amount' => 45, 'cost_interval' => 'monthly', 'end_date' => today()->addMonths(2), 'notice_period_months' => 1]);
        Loan::create(['property_id' => $house->id, 'bank' => 'Raiffeisenbank', 'contract_number' => 'KR-1002', 'principal' => 450000, 'interest_rate' => 2.1, 'start_date' => '2019-06-01', 'term_months' => 300, 'fixed_until' => today()->addMonths(4)]);
        MaintenanceTask::create(['property_id' => $top1->id, 'title' => 'Thermenwartung', 'interval_months' => 12, 'next_due_at' => today()->addDays(12)]);
        MaintenanceTask::create(['property_id' => $house->id, 'title' => 'Rauchfangkehrer', 'interval_months' => 6, 'next_due_at' => today()->subDays(3)]);
        $meter = Meter::create(['property_id' => $top2->id, 'type' => MeterType::Electricity, 'number' => 'AT0010000000000000001']);
        $meter->readings()->createMany([['read_at' => today()->subYear(), 'value' => 10240], ['read_at' => today(), 'value' => 11102]]);
        Ticket::create(['property_id' => $top1->id, 'title' => 'Wasserhahn Küche tropft', 'reported_at' => today()->subDays(4), 'priority' => 'normal']);
        Deadline::create(['title' => 'Steuererklärung V+V an Steuerberater', 'due_date' => today()->addDays(20)]);

        $json = json_decode(file_get_contents(base_path('tests/fixtures/statement.json')), true);
        $json['property_name'] = 'Wohnung Wien 7';
        app(StatementImporter::class)->import($json);
    }
}
