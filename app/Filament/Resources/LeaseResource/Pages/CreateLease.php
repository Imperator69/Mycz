<?php

namespace App\Filament\Resources\LeaseResource\Pages;

use App\Filament\Resources\LeaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLease extends CreateRecord
{
    protected static string $resource = LeaseResource::class;

    protected function afterFill(): void
    {
        if ($property = request()->integer('property')) {
            $this->data['property_id'] = $property;
        }
    }
}
