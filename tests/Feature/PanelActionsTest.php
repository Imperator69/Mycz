<?php

namespace Tests\Feature;

use App\Enums\RentChargeStatus;
use App\Enums\StatementStatus;
use App\Filament\Resources\LeaseResource\Pages\CreateLease;
use App\Filament\Resources\LeaseResource\Pages\EditLease;
use App\Filament\Resources\PropertyResource\Pages\CreateProperty;
use App\Filament\Resources\PropertyResource\Pages\EditProperty;
use App\Filament\Resources\PropertyResource\RelationManagers;
use App\Filament\Resources\RentChargeResource\Pages\ListRentCharges;
use App\Filament\Resources\StatementResource\Pages\EditStatement;
use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Models\Category;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\RentService;
use App\Services\StatementImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PanelActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeUser());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_property(): void
    {
        Livewire::test(CreateProperty::class)
            ->fillForm(['name' => 'Garage Melk', 'type' => 'garage', 'status' => 'vacant', 'city' => 'Melk'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('properties', ['name' => 'Garage Melk', 'type' => 'garage']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'subject_label' => 'Garage Melk']);
    }

    public function test_create_lease_generates_charges_and_marks_property_rented(): void
    {
        Carbon::setTestNow('2026-03-10');
        $property = $this->makeProperty(['status' => 'vacant']);
        $tenant = Tenant::create(['first_name' => 'Anna', 'last_name' => 'Berger']);

        Livewire::test(CreateLease::class)
            ->fillForm([
                'property_id' => $property->id, 'tenant_id' => $tenant->id, 'start_date' => '2026-02-01',
                'base_rent' => 500, 'operating_costs' => 100, 'heating_costs' => 0, 'other_costs' => 0,
                'vat_rate' => 10, 'due_day' => 3, 'charges_from' => '2026-02-01', 'index_type' => 'none',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, RentCharge::count());
        $this->assertEquals(660, (float) RentCharge::first()->amount_due);
        $this->assertSame('rented', $property->refresh()->status->value);
    }

    public function test_rent_adjustment_updates_open_future_charges(): void
    {
        Carbon::setTestNow('2026-03-10');
        $lease = $this->makeLease();
        app(RentService::class)->generateCharges();

        Livewire::test(EditLease::class, ['record' => $lease->getRouteKey()])
            ->callAction('adjust', data: ['base_rent' => 900, 'operating_costs' => 200, 'heating_costs' => 0, 'other_costs' => 0, 'from' => '2026-03-01', 'is_index' => true])
            ->assertHasNoActionErrors();

        $lease->refresh();
        $this->assertEquals(900, (float) $lease->base_rent);
        $this->assertSame('2026-03-01', $lease->index_last_adjusted_at->toDateString());
        $this->assertEquals(1210, (float) $lease->rentCharges()->whereDate('period', '2026-03-01')->first()->amount_due);
        $this->assertEquals(1100, (float) $lease->rentCharges()->whereDate('period', '2026-02-01')->first()->amount_due);
    }

    public function test_mark_rent_paid_from_list_and_bulk(): void
    {
        Carbon::setTestNow('2026-03-10');
        $this->makeLease();
        app(RentService::class)->generateCharges();
        [$jan, $feb, $mar] = RentCharge::orderBy('period')->get()->all();

        Livewire::test(ListRentCharges::class)
            ->assertCanSeeTableRecords([$jan, $feb])
            ->callTableAction('paid', $jan)
            ->callTableBulkAction('markPaid', [$feb, $mar], data: ['paid_at' => '2026-03-05']);

        $this->assertSame(3, RentCharge::where('status', RentChargeStatus::Paid)->count());
        $this->assertSame(3, Transaction::count());
        $this->assertEquals(3300, (float) Transaction::sum('amount_gross'));
    }

    public function test_create_transaction_computes_net(): void
    {
        $property = $this->makeProperty();

        Livewire::test(CreateTransaction::class)
            ->fillForm(['type' => 'expense'])
            ->fillForm([
                'category_id' => Category::bySlug('instandhaltung')->id,
                'property_id' => $property->id, 'booked_at' => '2026-05-01', 'description' => 'Fliesenleger',
                'amount_gross' => 1200, 'vat_rate' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tx = Transaction::first();
        $this->assertEquals(1000, (float) $tx->amount_net);
        $this->assertEquals(200, (float) $tx->vat_amount);
        $this->assertSame('expense', $tx->type->value);
    }

    public function test_statement_review_and_booking(): void
    {
        $this->makeProperty(['name' => 'Haus Melk']);
        $statement = app(StatementImporter::class)->import(json_decode(file_get_contents(base_path('tests/fixtures/statement.json')), true));

        Livewire::test(EditStatement::class, ['record' => $statement->getRouteKey()])
            ->assertFormSet(['issuer' => 'Hausverwaltung Muster GmbH'])
            ->callAction('bookLines');

        $this->assertSame(StatementStatus::Booked, $statement->refresh()->status);
        $this->assertSame(6, Transaction::where('statement_id', $statement->id)->count());
    }

    public function test_property_relation_managers_render(): void
    {
        $house = $this->makeProperty(['type' => 'house']);
        $this->makeProperty(['parent_id' => $house->id]);
        $this->makeLease($house);

        foreach ([
            RelationManagers\UnitsRelationManager::class,
            RelationManagers\LeasesRelationManager::class,
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\InsurancePoliciesRelationManager::class,
            RelationManagers\LoansRelationManager::class,
            RelationManagers\ContractsRelationManager::class,
            RelationManagers\MaintenanceTasksRelationManager::class,
            RelationManagers\MetersRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
        ] as $manager) {
            Livewire::test($manager, ['ownerRecord' => $house, 'pageClass' => EditProperty::class])->assertSuccessful();
        }
    }

    public function test_create_unit_from_property_relation_manager(): void
    {
        $house = $this->makeProperty(['type' => 'house']);

        Livewire::test(RelationManagers\LeasesRelationManager::class, ['ownerRecord' => $house, 'pageClass' => EditProperty::class])
            ->callTableAction('create', data: [
                'tenant_id' => Tenant::create(['last_name' => 'Huber'])->id,
                'start_date' => '2026-01-01', 'base_rent' => 400, 'operating_costs' => 0, 'heating_costs' => 0,
                'other_costs' => 0, 'vat_rate' => 10, 'due_day' => 5, 'charges_from' => '2026-01-01', 'index_type' => 'none',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $house->leases()->count());
        $this->assertGreaterThan(0, RentCharge::count());
    }
}
