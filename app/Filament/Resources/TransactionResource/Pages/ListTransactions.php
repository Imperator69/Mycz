<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Neue Buchung'),
            Actions\Action::make('export')->label('CSV '.date('Y'))->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->url(fn () => route('reports.export', ['format' => 'transactions', 'year' => date('Y')]), true),
        ];
    }
}
