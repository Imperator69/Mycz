<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeadlineResource;
use App\Services\DeadlineAggregator;
use Filament\Actions\Action;
use Filament\Pages\Page;

class Deadlines extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Übersicht';

    protected static ?string $title = 'Fristenkalender';

    protected static ?string $navigationLabel = 'Fristen';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.deadlines';

    protected static ?string $slug = 'fristenkalender';

    public int $days = 180;

    public ?string $category = null;

    public static function getNavigationBadge(): ?string
    {
        $count = app(DeadlineAggregator::class)->upcoming(14)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label('Eigene Erinnerung')->icon('heroicon-o-plus')->url(DeadlineResource::getUrl('create')),
        ];
    }

    protected function getViewData(): array
    {
        $items = app(DeadlineAggregator::class)->upcoming($this->days);

        return [
            'categories' => $items->pluck('category')->unique()->sort()->values(),
            'groups' => $items
                ->when($this->category, fn ($c) => $c->where('category', $this->category))
                ->groupBy(fn ($i) => $i['overdue'] ? 'Überfällig' : ucfirst($i['date']->translatedFormat('F Y'))),
        ];
    }
}
