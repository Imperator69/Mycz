<?php

namespace App\Filament\Resources\StatementResource\Pages;

use App\Enums\StatementStatus;
use App\Filament\Resources\StatementResource;
use App\Filament\Resources\TransactionResource;
use App\Services\GoogleDriveService;
use App\Services\StatementImporter;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStatement extends EditRecord
{
    protected static string $resource = StatementResource::class;

    protected function getHeaderActions(): array
    {
        $pending = fn () => $this->record->status === StatementStatus::PendingReview;

        return [
            Actions\Action::make('bookLines')->label('Positionen buchen')->icon('heroicon-o-check-badge')->color('success')
                ->visible($pending)
                ->requiresConfirmation()
                ->modalDescription('Jede Position wird als Ausgabe (bzw. Einnahme) zum Ende des Abrechnungszeitraums gebucht. Verwenden, wenn die monatlichen Vorschreibungen NICHT laufend gebucht werden.')
                ->action(fn () => $this->book('lines')),
            Actions\Action::make('bookBalance')->label('Nur Saldo buchen')->icon('heroicon-o-scale')->color('info')
                ->visible($pending)
                ->requiresConfirmation()
                ->modalDescription('Bucht nur die Nachzahlung bzw. das Guthaben. Verwenden, wenn die monatlichen Vorschreibungen bereits als Ausgaben gebucht sind.')
                ->action(fn () => $this->book('balance')),
            Actions\Action::make('reject')->label('Verwerfen')->icon('heroicon-o-x-mark')->color('gray')
                ->visible($pending)->requiresConfirmation()
                ->action(fn () => $this->record->update(['status' => StatementStatus::Rejected])),
            Actions\Action::make('transactions')->label('Buchungen ansehen')->icon('heroicon-o-arrows-right-left')->color('gray')
                ->visible(fn () => $this->record->status === StatementStatus::Booked)
                ->url(fn () => TransactionResource::getUrl('index')),
            Actions\DeleteAction::make()->visible($pending),
        ];
    }

    private function book(string $mode): void
    {
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        try {
            $count = app(StatementImporter::class)->book($this->record->refresh(), $mode);
            Notification::make()->success()->title("$count Buchung(en) erstellt")->send();
            $this->redirect(StatementResource::getUrl('index'));
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Buchen nicht möglich')->body(GoogleDriveService::describeError($e))->send();
        }
    }
}
