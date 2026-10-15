<?php

namespace Tests;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeUser(): User
    {
        return User::create(['name' => 'Test', 'email' => 'test'.uniqid().'@example.com', 'password' => bcrypt('secret-password')]);
    }

    protected function makeProperty(array $attributes = []): Property
    {
        return Property::create($attributes + [
            'name' => 'Wohnung Test '.uniqid(),
            'type' => PropertyType::Apartment,
            'status' => PropertyStatus::Rented,
            'city' => 'Wien',
            'purchase_price' => 200000,
            'purchase_costs' => 20000,
        ]);
    }

    protected function makeLease(?Property $property = null, array $attributes = []): Lease
    {
        $property ??= $this->makeProperty();
        $tenant = Tenant::create(['first_name' => 'Max', 'last_name' => 'Muster', 'iban' => 'AT611904300234573201', 'email' => 'max@example.com']);

        return Lease::create($attributes + [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'charges_from' => '2026-01-01',
            'base_rent' => 800,
            'operating_costs' => 200,
            'vat_rate' => 10,
            'due_day' => 5,
        ]);
    }
}
