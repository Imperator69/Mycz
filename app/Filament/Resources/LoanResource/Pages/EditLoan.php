<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Filament\Resources\LoanResource;
use App\Models\Category;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('bookYear')->label('Jahreswerte buchen')->icon('heroicon-o-calculator')
                ->modalDescription('Bucht Zinsen und Tilgung laut Tilgungsplan als Ausgaben zum 31.12. Bereits gebuchte Werte des Jahres werden aktualisiert.')
                ->form([
                    Forms\Components\Select::make('year')->label('Jahr')->required()->default((int) date('Y') - 1)
                        ->options(fn () => collect($this->record->schedule()->yearly())->pluck('year', 'year')->all()),
                ])
                ->action(function (array $data) {
                    $year = (int) $data['year'];
                    $totals = $this->record->schedule()->yearTotals($year);
                    foreach (['kreditzinsen' => 'interest', 'tilgung' => 'principal'] as $slug => $key) {
                        $category = Category::bySlug($slug);
                        $tx = Transaction::firstOrNew([
                            'loan_id' => $this->record->id,
                            'category_id' => $category->id,
                            'booked_at' => "$year-12-31",
                        ]);
                        $tx->fill([
                            'property_id' => $this->record->property_id,
                            'type' => TransactionType::Expense,
                            'description' => $category->name.' '.$year.' '.$this->record->bank,
                            'counterparty' => $this->record->bank,
                            'amount_net' => null,
                            'amount_gross' => $totals[$key],
                            'vat_rate' => 0,
                            'source' => TransactionSource::Loan,
                        ])->save();
                    }
                    Notification::make()->success()->title("Kredit $year gebucht")
                        ->body('Zinsen '.eur($totals['interest']).', Tilgung '.eur($totals['principal']))->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
