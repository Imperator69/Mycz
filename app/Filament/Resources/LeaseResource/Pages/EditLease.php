<?php

namespace App\Filament\Resources\LeaseResource\Pages;

use App\Enums\RentChargeStatus;
use App\Filament\Resources\LeaseResource;
use App\Filament\Support\Fields;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditLease extends EditRecord
{
    protected static string $resource = LeaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('adjust')->label('Miete anpassen')->icon('heroicon-o-arrow-trending-up')
                ->modalDescription('Z.B. nach Wertsicherung (VPI). Offene Soll-Positionen ab dem gewählten Monat werden mit angepasst.')
                ->fillForm(fn () => [
                    'base_rent' => $this->record->base_rent,
                    'operating_costs' => $this->record->operating_costs,
                    'heating_costs' => $this->record->heating_costs,
                    'other_costs' => $this->record->other_costs,
                    'from' => today()->startOfMonth()->addMonth(),
                    'index_value' => $this->record->index_base_value,
                ])
                ->form([
                    Forms\Components\Grid::make(4)->schema([
                        Fields::money('base_rent', 'Hauptmietzins')->required(),
                        Fields::money('operating_costs', 'BK-Akonto')->required(),
                        Fields::money('heating_costs', 'Heizkosten')->required(),
                        Fields::money('other_costs', 'Sonstiges')->required(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\DatePicker::make('from')->label('Gültig ab Monat')->required(),
                        Forms\Components\TextInput::make('index_value')->label('Neuer Index-Basiswert (optional)')->numeric(),
                        Forms\Components\Toggle::make('is_index')->label('Wertsicherungsanpassung')->default(true)->inline(false),
                    ]),
                ])
                ->action(function (array $data) {
                    $from = Carbon::parse($data['from'])->startOfMonth();
                    $lease = $this->record;
                    $lease->fill(collect($data)->only(['base_rent', 'operating_costs', 'heating_costs', 'other_costs'])->all());
                    if ($data['is_index'] ?? false) {
                        $lease->index_last_adjusted_at = $from;
                        if (filled($data['index_value'])) {
                            $lease->index_base_value = $data['index_value'];
                        }
                    }
                    $lease->notes = trim(($lease->notes ? $lease->notes."\n" : '').'Mietanpassung ab '.$from->format('m/Y').': '.eur($lease->monthlyGross()).' brutto');
                    $lease->save();

                    $updated = $lease->rentCharges()->where('status', RentChargeStatus::Open)->whereDate('period', '>=', $from)
                        ->update(['amount_due' => $lease->monthlyGross()]);

                    $this->refreshFormData(['base_rent', 'operating_costs', 'heating_costs', 'other_costs', 'index_last_adjusted_at', 'index_base_value', 'notes']);
                    Notification::make()->success()->title('Miete angepasst')->body('Neu: '.eur($lease->monthlyGross())." brutto, $updated offene Position(en) aktualisiert.")->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
