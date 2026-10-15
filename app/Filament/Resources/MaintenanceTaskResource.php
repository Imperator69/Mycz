<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceTaskResource\Pages;
use App\Filament\Support\Fields;
use App\Models\MaintenanceTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class MaintenanceTaskResource extends Resource
{
    protected static ?string $model = MaintenanceTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Wartung';

    protected static ?string $pluralModelLabel = 'Wartung & Prüfungen';

    protected static ?int $navigationSort = 4;

    public const PRESETS = [
        'Thermenwartung' => 12, 'Rauchmelder prüfen' => 12, 'Rauchfangkehrer' => 6, 'Elektrobefund (E-Check)' => 60,
        'Gasleitung Dichtheitsprüfung' => 144, 'Feuerlöscher Überprüfung' => 24, 'Dachrinne reinigen' => 12,
        'Klimaanlage Service' => 12, 'Legionellenprüfung' => 36,
    ];

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Fields::property(),
            Forms\Components\TextInput::make('title')->label('Aufgabe')->required()->datalist(array_keys(self::PRESETS))
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get, ?string $state) => $get('interval_months') ? null : $set('interval_months', self::PRESETS[$state] ?? null)),
            Forms\Components\Select::make('contact_id')->label('Dienstleister')->relationship('contact', 'name')->searchable()->preload(),
            Forms\Components\TextInput::make('interval_months')->label('Intervall')->numeric()->suffix('Monate'),
            Forms\Components\DatePicker::make('last_done_at')->label('Zuletzt erledigt'),
            Forms\Components\DatePicker::make('next_due_at')->label('Nächster Termin'),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('next_due_at')
            ->columns([
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('title')->label('Aufgabe')->searchable(),
                Tables\Columns\TextColumn::make('interval_months')->label('Intervall')->suffix(' Mon.')->placeholder('einmalig'),
                Tables\Columns\TextColumn::make('last_done_at')->label('Zuletzt')->date()->placeholder('–'),
                Tables\Columns\TextColumn::make('next_due_at')->label('Fällig')->date()->sortable()->placeholder('–')
                    ->color(fn (MaintenanceTask $r) => $r->next_due_at?->lt(today()) ? 'danger' : ($r->next_due_at?->lt(today()->addDays(30)) ? 'warning' : null)),
                Tables\Columns\TextColumn::make('contact.name')->label('Dienstleister')->placeholder('–'),
            ])
            ->filters([Fields::propertyFilter()->hidden(fn ($livewire) => $livewire instanceof RelationManager)])
            ->actions([
                Tables\Actions\Action::make('done')->label('Erledigt')->icon('heroicon-o-check')->color('success')
                    ->form([Forms\Components\DatePicker::make('on')->label('Erledigt am')->default(today())->required()])
                    ->action(fn (MaintenanceTask $r, array $data) => $r->markDone(Carbon::parse($data['on']))),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceTasks::route('/'),
            'create' => Pages\CreateMaintenanceTask::route('/create'),
            'edit' => Pages\EditMaintenanceTask::route('/{record}/edit'),
        ];
    }
}
