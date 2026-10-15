<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $modelLabel = 'Aktivität';

    protected static ?string $pluralModelLabel = 'Aktivitätslog';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Zeit')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Benutzer')->placeholder('System'),
                Tables\Columns\TextColumn::make('action')->label('Aktion')->badge()
                    ->formatStateUsing(fn (string $state) => ['created' => 'Angelegt', 'updated' => 'Geändert', 'deleted' => 'Gelöscht'][$state] ?? $state)
                    ->color(fn (string $state) => ['created' => 'success', 'updated' => 'info', 'deleted' => 'danger'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('subject_type')->label('Typ')->formatStateUsing(fn (string $state) => class_basename($state)),
                Tables\Columns\TextColumn::make('subject_label')->label('Datensatz')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('changes')->label('Änderungen')
                    ->state(fn (ActivityLog $r) => $r->changes ? Str::limit(collect($r->changes)->map(fn ($v, $k) => $k.': '.(is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v)))->join(', '), 120) : null)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')->label('Aktion')->options(['created' => 'Angelegt', 'updated' => 'Geändert', 'deleted' => 'Gelöscht']),
                Tables\Filters\SelectFilter::make('user_id')->label('Benutzer')->relationship('user', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListActivityLogs::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
