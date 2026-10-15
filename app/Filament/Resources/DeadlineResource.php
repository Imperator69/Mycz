<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeadlineResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Deadline;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeadlineResource extends Resource
{
    protected static ?string $model = Deadline::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Erinnerung';

    protected static ?string $pluralModelLabel = 'Eigene Erinnerungen';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\TextInput::make('title')->label('Titel')->required()->columnSpan(2),
            Fields::property(required: false),
            Forms\Components\DatePicker::make('due_date')->label('Fällig am')->required(),
            Forms\Components\TextInput::make('repeat_months')->label('Wiederholen alle')->numeric()->suffix('Monate')->helperText('Leer = einmalig'),
            Forms\Components\DatePicker::make('done_at')->label('Erledigt am'),
            Forms\Components\Textarea::make('notes')->label('Notizen')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                Tables\Columns\TextColumn::make('due_date')->label('Fällig')->date()->sortable()
                    ->color(fn (Deadline $r) => ! $r->done_at && $r->due_date->lt(today()) ? 'danger' : null),
                Tables\Columns\TextColumn::make('title')->label('Titel')->searchable(),
                Fields::propertyColumn(),
                Tables\Columns\TextColumn::make('repeat_months')->label('Wiederholung')->suffix(' Mon.')->placeholder('einmalig'),
                Tables\Columns\TextColumn::make('done_at')->label('Erledigt')->date()->placeholder('–'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('open')->label('Status')->trueLabel('Offen')->falseLabel('Erledigt')->default(true)
                    ->queries(true: fn (Builder $query) => $query->whereNull('done_at'), false: fn (Builder $query) => $query->whereNotNull('done_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')->label('Erledigt')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Deadline $r) => ! $r->done_at)
                    ->action(fn (Deadline $r) => $r->complete()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeadlines::route('/'),
            'create' => Pages\CreateDeadline::route('/create'),
            'edit' => Pages\EditDeadline::route('/{record}/edit'),
        ];
    }
}
