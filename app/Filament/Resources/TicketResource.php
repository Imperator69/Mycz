<?php

namespace App\Filament\Resources;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Support\Fields;
use App\Models\InsuranceClaim;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Schaden / Aufgabe';

    protected static ?string $pluralModelLabel = 'Schäden & Aufgaben';

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $count = Ticket::whereNot('status', TicketStatus::Done)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Fields::property(),
            Forms\Components\TextInput::make('title')->label('Titel')->required()->columnSpan(2),
            Forms\Components\Textarea::make('description')->label('Beschreibung')->rows(4)->columnSpanFull(),
            Forms\Components\Select::make('status')->label('Status')->options(TicketStatus::class)->default(TicketStatus::Open)->required()->live(),
            Forms\Components\Select::make('priority')->label('Priorität')->options(TicketPriority::class)->default(TicketPriority::Normal)->required(),
            Forms\Components\Select::make('contact_id')->label('Zuständig / Handwerker')->relationship('contact', 'name')->searchable()->preload(),
            Forms\Components\DatePicker::make('reported_at')->label('Gemeldet am')->default(today())->required(),
            Forms\Components\DatePicker::make('resolved_at')->label('Erledigt am'),
            Fields::money('cost_estimate', 'Kosten (Schätzung)'),
            Forms\Components\Select::make('insurance_claim_id')->label('Versicherungsfall')
                ->relationship('insuranceClaim', 'claim_number')
                ->getOptionLabelFromRecordUsing(fn (InsuranceClaim $c) => ($c->claim_number ?: 'Schaden').' vom '.$c->incident_date->format('d.m.Y').' ('.$c->policy?->insurer.')')
                ->searchable()->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reported_at', 'desc')
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('title')->label('Titel')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('priority')->label('Priorität')->badge()
                    ->color(fn (TicketPriority $state) => match ($state) {
                        TicketPriority::Urgent => 'danger', TicketPriority::High => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (TicketStatus $state) => $state === TicketStatus::Done ? 'success' : ($state === TicketStatus::Open ? 'danger' : 'warning')),
                Tables\Columns\TextColumn::make('reported_at')->label('Gemeldet')->date()->sortable(),
                Tables\Columns\TextColumn::make('contact.name')->label('Zuständig')->placeholder('–'),
                Fields::moneyColumn('cost_estimate', 'Kosten')->toggleable(),
            ])
            ->filters([
                Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager),
                Tables\Filters\SelectFilter::make('status')->label('Status')->multiple()->options(TicketStatus::class)
                    ->default([TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::Waiting->value]),
            ])
            ->actions([
                Tables\Actions\Action::make('done')->label('Erledigt')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Ticket $r) => $r->status !== TicketStatus::Done)
                    ->action(fn (Ticket $r) => $r->update(['status' => TicketStatus::Done, 'resolved_at' => today()])),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
