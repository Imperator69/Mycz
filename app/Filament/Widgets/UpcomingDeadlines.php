<?php

namespace App\Filament\Widgets;

use App\Services\DeadlineAggregator;
use Filament\Widgets\Widget;

class UpcomingDeadlines extends Widget
{
    protected static string $view = 'filament.widgets.upcoming-deadlines';

    protected static ?int $sort = 3;

    public int $days = 60;

    public int $limit = 10;

    protected function getViewData(): array
    {
        return ['items' => app(DeadlineAggregator::class)->upcoming($this->days)->take($this->limit)];
    }
}
