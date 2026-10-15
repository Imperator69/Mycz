<?php

namespace App\Filament\Resources\RentChargeResource\Pages;

use App\Filament\Resources\RentChargeResource;
use App\Services\RentService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRentCharges extends ListRecords
{
    protected static string $resource = RentChargeResource::class;

    protected ?string $subheading = 'Monatliche Soll-Mieten. Zahlungseingänge am Konto prüfen und hier abhaken.';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')->label('Soll-Mieten aktualisieren')->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $count = app(RentService::class)->generateCharges();
                    Notification::make()->success()->title($count.' neue Soll-Positionen angelegt')->send();
                }),
        ];
    }
}
